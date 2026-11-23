<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function cancelar(Nfse $nfse, string $codigoUi): array
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

            $consultaPrevia = $this->consultarEventoCancelamento($client, $chave, $certificado);

            if ($consultaPrevia instanceof NfseSefinEventoResposta && $consultaPrevia->registrado) {
                return $this->confirmarLocalmente($nfse, $codigoUi, $consultaPrevia);
            }

            $xmlAssinado = $this->assinador->assinarCancelamento(
                $nfse,
                $this->xml->gerarCancelamento($nfse, $codigoUi),
            );
            $requisicao = $client->prepararEvento($chave, $xmlAssinado, $certificado);

            try {
                [$http, $body] = $this->http->executar($requisicao);
                $resposta = NfseSefinEventoResposta::interpretar($http, $body);
            } catch (NfseNaoTransmitida $exception) {
                $resposta = $this->reconciliarAposIncerteza($client, $chave, $certificado, $exception->getMessage());
            } catch (Throwable $exception) {
                report($exception);
                $resposta = $this->reconciliarAposIncerteza(
                    $client,
                    $chave,
                    $certificado,
                    'Falha de comunicação com a SEFIN Nacional.',
                );
            }

            if ($resposta->jaCancelada && ($resposta->xmlEvento === null || ! $resposta->registrado)) {
                $consultada = $this->consultarEventoCancelamento($client, $chave, $certificado);
                if ($consultada instanceof NfseSefinEventoResposta && $consultada->registrado) {
                    $resposta = $consultada;
                } elseif ($resposta->jaCancelada) {
                    $resposta = new NfseSefinEventoResposta(
                        http: $resposta->http,
                        registrado: true,
                        jaCancelada: true,
                        xmlEvento: $resposta->xmlEvento,
                        protocolo: $resposta->protocolo,
                        dataHoraProcessamento: $resposta->dataHoraProcessamento,
                        tipoAmbiente: $resposta->tipoAmbiente,
                        versaoAplicativo: $resposta->versaoAplicativo,
                        alertas: $resposta->alertas,
                        erros: [],
                    );
                }
            }

            if (! $resposta->registrado) {
                $this->liberar($nfse);

                return [
                    'nfse' => $nfse->refresh(),
                    'cancelada' => false,
                    'erros' => $resposta->erros !== [] ? $resposta->erros : [[
                        'codigo' => '',
                        'descricao' => 'A SEFIN Nacional não confirmou o cancelamento.',
                    ]],
                ];
            }

            return $this->confirmarLocalmente($nfse, $codigoUi, $resposta);
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
        string $motivo,
    ): NfseSefinEventoResposta {
        Log::warning('NFS-e Nacional: cancelamento com retorno incerto; consultando evento.', [
            'chave' => $chave,
            'motivo' => $motivo,
        ]);

        $consultada = $this->consultarEventoCancelamento($client, $chave, $certificado);

        if ($consultada instanceof NfseSefinEventoResposta && $consultada->registrado) {
            return $consultada;
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

    private function consultarEventoCancelamento(
        NfseSefinClient $client,
        string $chave,
        Certificate $certificado,
    ): ?NfseSefinEventoResposta {
        try {
            $requisicao = $client->prepararConsultaEvento(
                $chave,
                NfseSefinEventoXmlGerador::TIPO_CANCELAMENTO,
                NfseSefinEventoXmlGerador::SEQ_CANCELAMENTO,
                $certificado,
            );
            [$http, $body] = $this->http->executar($requisicao);
            $resposta = NfseSefinEventoResposta::interpretar($http, $body);

            return $resposta->registrado ? $resposta : null;
        } catch (Throwable $exception) {
            Log::info('NFS-e Nacional: consulta de evento de cancelamento sem confirmação.', [
                'chave' => $chave,
                'erro' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{nfse: Nfse, cancelada: bool, erros: list<array{codigo: string, descricao: string}>}
     */
    private function confirmarLocalmente(Nfse $nfse, string $codigoUi, NfseSefinEventoResposta $resposta): array
    {
        $motivo = NfseSefinEventoXmlGerador::mapearMotivo($codigoUi);

        $nfse->fill([
            'status' => Nfse::STATUS_CANCELADA,
            'cancelamento_codigo' => $motivo['codigo'],
            'cancelada_em' => now(),
            'cancelada_por' => mb_substr(trim((string) (Auth::user()?->name ?? '')), 0, 120) ?: null,
            'xml_cancelamento' => $resposta->xmlEvento,
            'protocolo' => $resposta->protocolo ?: $nfse->protocolo,
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
