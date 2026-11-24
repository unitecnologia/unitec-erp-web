<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseSefinCancelarService
{
    public function __construct(
        private readonly NfseSefinHttp $http = new NfseSefinHttp,
        private readonly NfseSefinEventoXmlGerador $xml = new NfseSefinEventoXmlGerador,
        private readonly NfseSefinEventoAssinador $assinador = new NfseSefinEventoAssinador,
        private readonly NfseDpsAssinador $certificados = new NfseDpsAssinador,
    ) {}

    /**
     * @return array{nfse: Nfse, cancelada: bool, erros: list<array{codigo: string, descricao: string}>}
     */
    public function cancelar(Nfse $nfse, string $codigoUi, ?string $xMotivoLivre = null): array
    {
        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        if (strtolower(trim((string) $empresa->nfse_provedor)) !== 'nacional') {
            throw new NfseNaoTransmitida('O cancelamento SEFIN Nacional só se aplica ao provedor Nacional.');
        }

        $this->garantirNumeroNfse($nfse);
        $this->reservar($nfse);

        try {
            $chave = $this->chave($nfse);
            $certificado = $this->certificados->certificadoDaNota($nfse);
            $client = NfseSefinClient::daEmpresa($empresa);

            // Sempre consulta GET 101101/001 antes de qualquer novo POST.
            // Se já houver evento registrado, confirma localmente sem reenviar.
            // Se a consulta falhar, não POST — evita duplicar evento já aceito.
            $consultaPrevia = $this->consultarEventoCancelamento($client, $chave, $certificado, $nfse);

            if ($consultaPrevia['status'] === NfseSefinCancelamentoConsulta::REGISTRADO) {
                NfseSefinCancelamentoLog::falha(
                    NfseSefinCancelamentoLog::OPERACAO_GET_CONSULTA,
                    $nfse,
                    $chave,
                    [
                        'http' => $consultaPrevia['resposta']?->http ?? 200,
                        'registrado' => true,
                        'resultado_consulta' => 'confirmado_por_consulta_previa',
                        'motivo' => 'Cancelamento já registrado na SEFIN; nenhum novo POST foi enviado.',
                    ],
                );

                return $this->confirmarLocalmente($nfse, $codigoUi, $consultaPrevia['resposta'], $xMotivoLivre);
            }

            if ($consultaPrevia['status'] === NfseSefinCancelamentoConsulta::ERRO_COMUNICACAO) {
                $this->liberar($nfse);

                return [
                    'nfse' => $nfse->refresh(),
                    'cancelada' => false,
                    'erros' => [[
                        'codigo' => '',
                        'descricao' => 'Não foi possível consultar o evento de cancelamento na SEFIN. '
                            .'Tente novamente sem reenviar até a consulta responder, para evitar duplicidade.',
                    ]],
                ];
            }

            $xmlAssinado = $this->assinador->assinarCancelamento(
                $nfse,
                $this->xml->gerarCancelamento($nfse, $codigoUi, $xMotivoLivre),
            );
            $requisicao = $client->prepararEvento($chave, $xmlAssinado, $certificado);

            try {
                [$http, $body] = $this->http->executar($requisicao);
                $resposta = NfseSefinEventoResposta::interpretar($http, $body);
            } catch (NfseNaoTransmitida $exception) {
                $resposta = $this->reconciliarAposIncerteza(
                    $client,
                    $chave,
                    $certificado,
                    $nfse,
                    $exception->getMessage(),
                );
            } catch (Throwable $exception) {
                report($exception);
                $resposta = $this->reconciliarAposIncerteza(
                    $client,
                    $chave,
                    $certificado,
                    $nfse,
                    'Falha de comunicação com a SEFIN Nacional.',
                );
            }

            // E0840 / "já cancelada" sem XML: só confirma se o GET 101101/001 trouxer e101101.
            // E0840 sozinho nunca confirma (pode ser confirmação do tomador, análise fiscal, etc.).
            if (
                ! $resposta->registrado
                && (
                    $resposta->jaCancelada
                    || NfseSefinEventoResposta::exigeConsultaConfirmacao($resposta->erros)
                )
            ) {
                $consultada = $this->consultarEventoCancelamento($client, $chave, $certificado, $nfse);

                if ($consultada['status'] === NfseSefinCancelamentoConsulta::REGISTRADO) {
                    return $this->confirmarLocalmente($nfse, $codigoUi, $consultada['resposta'], $xMotivoLivre);
                }
            }

            if (! $resposta->registrado) {
                $this->liberar($nfse);
                $erros = $resposta->erros !== [] ? $resposta->erros : [[
                    'codigo' => '',
                    'descricao' => 'A SEFIN Nacional não confirmou o cancelamento.',
                ]];
                $motivo = $resposta->mensagemErros();
                if ($motivo === '') {
                    $motivo = $erros[0]['descricao'] ?? 'A SEFIN Nacional não confirmou o cancelamento.';
                }

                NfseSefinCancelamentoLog::falha(
                    NfseSefinCancelamentoLog::OPERACAO_POST_CANCELAMENTO,
                    $nfse,
                    $chave,
                    [
                        'http' => $resposta->http,
                        'registrado' => false,
                        'ja_cancelada' => $resposta->jaCancelada,
                        'erros' => $erros,
                        'motivo' => $motivo,
                        'resultado_consulta' => 'nao_confirmado',
                    ],
                );

                return [
                    'nfse' => $nfse->refresh(),
                    'cancelada' => false,
                    'erros' => $erros,
                ];
            }

            return $this->confirmarLocalmente($nfse, $codigoUi, $resposta, $xMotivoLivre);
        } catch (NfseNaoTransmitida $exception) {
            $this->liberar($nfse);

            throw $exception;
        } catch (NfseSefinNaoEnviada|NfseDpsNaoAssinada $exception) {
            $this->liberar($nfse);

            throw new NfseNaoTransmitida($exception->getMessage());
        } catch (Throwable $exception) {
            $this->liberar($nfse);
            report($exception);

            throw new NfseNaoTransmitida('Não foi possível enviar o cancelamento à SEFIN Nacional.');
        }
    }

    private function reconciliarAposIncerteza(
        NfseSefinClient $client,
        string $chave,
        Certificate $certificado,
        Nfse $nfse,
        string $motivo,
    ): NfseSefinEventoResposta {
        NfseSefinCancelamentoLog::falha(
            NfseSefinCancelamentoLog::OPERACAO_POST_CANCELAMENTO,
            $nfse,
            $chave,
            [
                'http' => 0,
                'registrado' => false,
                'motivo' => $motivo,
                'resultado_consulta' => 'incerto_aguardando_consulta',
            ],
        );

        $consultada = $this->consultarEventoCancelamento($client, $chave, $certificado, $nfse);

        if ($consultada['status'] === NfseSefinCancelamentoConsulta::REGISTRADO) {
            return $consultada['resposta'];
        }

        return new NfseSefinEventoResposta(
            http: 0,
            registrado: false,
            jaCancelada: false,
            xmlEvento: null,
            protocolo: null,
            dataHoraProcessamento: null,
            tipoAmbiente: null,
            versaoAplicativo: null,
            alertas: [],
            erros: [[
                'codigo' => '',
                'descricao' => $motivo.' Consulte a SEFIN antes de tentar novamente para evitar duplicidade.',
            ]],
        );
    }

    /**
     * @return array{
     *     status: string,
     *     resposta: ?NfseSefinEventoResposta,
     *     motivo: ?string
     * }
     */
    private function consultarEventoCancelamento(
        NfseSefinClient $client,
        string $chave,
        Certificate $certificado,
        Nfse $nfse,
    ): array {
        try {
            $requisicao = $client->prepararConsultaEvento(
                $chave,
                NfseSefinEventoXmlGerador::TIPO_CANCELAMENTO,
                NfseSefinEventoXmlGerador::SEQ_CANCELAMENTO,
                $certificado,
            );
            [$http, $body] = $this->http->executar($requisicao);
            $resultado = NfseSefinCancelamentoConsulta::classificar($http, $body);
        } catch (Throwable $exception) {
            $resultado = [
                'status' => NfseSefinCancelamentoConsulta::ERRO_COMUNICACAO,
                'resposta' => null,
                'motivo' => $exception->getMessage(),
            ];
        }

        if ($resultado['status'] === NfseSefinCancelamentoConsulta::REGISTRADO) {
            return $resultado;
        }

        NfseSefinCancelamentoLog::falha(
            NfseSefinCancelamentoLog::OPERACAO_GET_CONSULTA,
            $nfse,
            $chave,
            [
                'http' => $resultado['resposta']?->http ?? 0,
                'registrado' => false,
                'ja_cancelada' => $resultado['resposta']?->jaCancelada ?? false,
                'erros' => $resultado['resposta']?->erros ?? [],
                'resultado_consulta' => $resultado['status'],
                'motivo' => $resultado['motivo'] ?? 'A SEFIN Nacional não confirmou o cancelamento.',
            ],
        );

        return $resultado;
    }

    /**
     * @return array{nfse: Nfse, cancelada: bool, erros: list<array{codigo: string, descricao: string}>}
     */
    private function confirmarLocalmente(
        Nfse $nfse,
        string $codigoUi,
        NfseSefinEventoResposta $resposta,
        ?string $xMotivoLivre = null,
    ): array {
        $motivo = NfseSefinEventoXmlGerador::mapearMotivo($codigoUi, $xMotivoLivre);

        // protocolo varchar(40) guarda o da emissão. Id EVT (~62) fica só em xml_cancelamento.
        $nfse->fill([
            'status' => Nfse::STATUS_CANCELADA,
            'cancelamento_codigo' => $motivo['codigo'],
            'cancelada_em' => now(),
            'cancelada_por' => mb_substr(trim((string) (Auth::user()?->name ?? '')), 0, 120) ?: null,
            'xml_cancelamento' => $resposta->xmlEvento,
            'protocolo' => self::protocoloParaGravar($resposta->protocolo, $nfse->protocolo),
        ]);
        $nfse->save();
        Nfse::query()->whereKey($nfse->id)->update(['transmitindo_em' => null]);

        $nfse = $nfse->fresh(['empresa']) ?? $nfse;
        $empresaPortal = $nfse->empresa;

        if ($empresaPortal instanceof Empresa) {
            (new ContadorCloudPortalHookService)->onNfseCancelada($nfse, $empresaPortal);
        }

        return ['nfse' => $nfse, 'cancelada' => true, 'erros' => []];
    }

    /**
     * Nunca substitui o protocolo da emissão pelo Id EVT do cancelamento (SQL 1406 / varchar 40).
     */
    public static function protocoloParaGravar(?string $protocoloEvento, mixed $protocoloEmissao): ?string
    {
        $emissao = trim((string) ($protocoloEmissao ?? ''));
        $evento = trim((string) ($protocoloEvento ?? ''));

        if ($emissao !== '') {
            return mb_substr($emissao, 0, 40, 'UTF-8');
        }

        if ($evento === '' || str_starts_with(strtoupper($evento), 'EVT')) {
            return null;
        }

        return mb_strlen($evento, 'UTF-8') <= 40 ? $evento : null;
    }

    private function garantirNumeroNfse(Nfse $nfse): void
    {
        if (filled($nfse->numero_nfse)) {
            return;
        }

        $numero = NfseSefinXmlLeitor::numeroNfse($nfse->xml_nfse);

        if ($numero === null) {
            return;
        }

        $nfse->numero_nfse = $numero;
        $nfse->save();
    }

    private function chave(Nfse $nfse): string
    {
        $chave = preg_replace('/\D/', '', (string) ($nfse->chave_acesso ?: $nfse->chave)) ?? '';

        if (strlen($chave) !== 50) {
            throw new NfseNaoTransmitida('A NFS-e Nacional não tem chave de acesso válida para cancelar.');
        }

        return $chave;
    }

    private function reservar(Nfse $nfse): void
    {
        DB::transaction(function () use ($nfse): void {
            $atual = Nfse::query()->whereKey($nfse->id)->lockForUpdate()->first();

            if ($atual === null || $atual->status !== Nfse::STATUS_AUTORIZADA) {
                throw new NfseNaoTransmitida('Só é possível cancelar NFS-e autorizada.');
            }

            $chave = preg_replace('/\D/', '', (string) ($atual->chave_acesso ?: $atual->chave)) ?? '';

            if (strlen($chave) !== 50) {
                throw new NfseNaoTransmitida('Só é possível cancelar NFS-e Nacional autorizada com chave de acesso.');
            }

            if ($atual->transmitindo_em !== null && $atual->transmitindo_em->gt(now()->subSeconds(90))) {
                throw new NfseNaoTransmitida('Esta NFS-e já está sendo enviada à SEFIN. Aguarde.');
            }

            $atual->transmitindo_em = now();
            $atual->save();
        });
    }

    private function liberar(Nfse $nfse): void
    {
        Nfse::query()->whereKey($nfse->id)->update(['transmitindo_em' => null]);
    }
}
