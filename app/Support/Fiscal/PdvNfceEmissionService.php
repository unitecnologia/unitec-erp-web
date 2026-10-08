<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\Nfce\NfceTentativaHistorico;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\TerminalResolver;
use Carbon\CarbonInterface;
use Throwable;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\ConsultarNfceResponse;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\EmitirNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

/**
 * Emissão NFC-e com integridade fiscal: número, chave e XML assinado são gravados (status pendente)
 * ANTES do envio. Sem retorno conclusivo da SEFAZ, a chave é consultada antes de qualquer decisão;
 * situação desconhecida mantém a nota em Gravados e bloqueia nova emissão.
 */
final class PdvNfceEmissionService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
        private readonly PdvNfceConsultaService $consultaService = new PdvNfceConsultaService(),
    ) {}

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     * @param  CarbonInterface|null  $dataEmissao  dhEmi; padrão = fechamento da venda
     * @param  bool  $permitirContingencia  false: SEFAZ indisponível vira erro (sem NFC-e offline)
     */
    public function emitir(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        ?callable $onProgress = null,
        ?CarbonInterface $dataEmissao = null,
        bool $permitirContingencia = true,
    ): PdvVendaNfce {
        CaBundleResolver::setProjectRoot(base_path());

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR, 'nfce');

        $registro = $this->registroReaproveitavel($venda);
        $terminal = TerminalResolver::make()->current();
        $serieNfce = NfceTerminalSequencia::serieEfetivaInt($terminal, $parametros);
        $numeroNfce = NfceTerminalSequencia::consume($terminal, $parametros);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML, 'nfce');

        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            $operacao,
            $numeroNfce,
            serieNfce: $serieNfce,
            dataEmissao: $dataEmissao,
        );

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR, 'nfce');

        $prepared = $this->engine->prepararNfceAssinada($request);
        $nfce = $this->gravarAntesDoEnvio($registro, $venda, $empresa, $operacao, $request, $prepared, $parametros, $dataEmissao);

        return $this->enviarRegistro($nfce, $empresa, $parametros, $onProgress, $permitirContingencia, $request->certificate);
    }

    /**
     * Envia o XML assinado já gravado no registro (mesma chave). Usado na emissão e na retransmissão
     * de nota pendente, para que um reenvio nunca gere chave diferente da já recebida pela SEFAZ.
     *
     * @param  (callable(int, string): void)|null  $onProgress
     */
    public function enviarRegistro(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        ?callable $onProgress = null,
        bool $permitirContingencia = false,
        ?Certificate $certificate = null,
    ): PdvVendaNfce {
        CaBundleResolver::setProjectRoot(base_path());

        $nfeXml = NfceXmlProtocolo::nfe($nfce->xml);

        if ($nfeXml === null || blank($nfce->chave)) {
            throw new FiscalEngineException('NFC-e sem XML assinado gravado para envio.');
        }

        $certificate ??= NfceFiscalCertificateResolver::resolve($empresa, $parametros);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_SEFAZ, 'nfce');

        try {
            $response = $this->engine->autorizarNfceAssinada(
                nfeXml: $nfeXml,
                certificate: $certificate,
                tpAmb: NfceFiscalCertificateResolver::tpAmb($parametros),
                chave: (string) $nfce->chave,
                qrCodeUrl: (string) $nfce->qr_code_conteudo,
                numero: (int) $nfce->numero,
                serie: self::serieInt($nfce),
                cNf: (int) ltrim((string) $nfce->cnf, '0'),
            );
        } catch (FiscalEngineException $exception) {
            return $this->tratarFalhaEnvio($nfce, $empresa, $parametros, $certificate, $exception, $permitirContingencia);
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_AUTORIZACAO, 'nfce');

        return $this->marcarAutorizada($nfce, $empresa, $response);
    }

    /**
     * Remonta e reenvia nota pendente/rejeitada: mesmo número e cNF (mesma chave no mês da emissão),
     * ou novo número quando o anterior já está em uso na SEFAZ (539).
     *
     * @param  (callable(int, string): void)|null  $onProgress
     */
    public function reemitirRegistro(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        bool $novoNumero,
        ?callable $onProgress = null,
    ): PdvVendaNfce {
        CaBundleResolver::setProjectRoot(base_path());

        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e sem venda vinculada para transmissão.');
        }

        $novoNumero = $novoNumero || blank($nfce->numero);
        $operacao = (string) ($nfce->operacao ?: PdvFinalizarOperacao::NFCE_TRANSMITIR);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML, 'nfce');

        if ($novoNumero) {
            $terminal = TerminalResolver::make()->current();
            $serie = NfceTerminalSequencia::serieEfetivaInt($terminal, $parametros);
            $numero = NfceTerminalSequencia::consume($terminal, $parametros);
            $cNf = null;
        } else {
            $serie = self::serieInt($nfce);
            $numero = (int) $nfce->numero;
            $cNf = ((int) ltrim((string) $nfce->cnf, '0')) ?: null;
        }

        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            $operacao,
            $numero,
            $cNf,
            serieNfce: $serie,
        );

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR, 'nfce');

        $prepared = $this->engine->prepararNfceAssinada($request);
        $nfce = $this->gravarAntesDoEnvio($nfce, $venda, $empresa, $operacao, $request, $prepared, $parametros, null);

        return $this->enviarRegistro($nfce, $empresa, $parametros, $onProgress, false, $request->certificate);
    }

    public function emitirContingencia(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        ?int $numeroNfce = null,
        ?string $motivoContingencia = null,
        ?PdvVendaNfce $registro = null,
        ?int $serieNfce = null,
    ): PdvVendaNfce {
        CaBundleResolver::setProjectRoot(base_path());

        $registro ??= $this->registroReaproveitavel($venda);
        $numeroNfce ??= NfceTerminalSequencia::consume(TerminalResolver::make()->current(), $parametros);
        $justificativa = NfceContingenciaJustificativa::normalize($motivoContingencia);
        $serieNfce ??= NfceTerminalSequencia::serieEfetivaInt(TerminalResolver::make()->current(), $parametros);
        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
            $numeroNfce,
            justificativaContingencia: $justificativa,
            serieNfce: $serieNfce,
        );
        $response = $this->engine->prepararNfceContingencia($request);
        $ambiente = NfceFiscalCertificateResolver::ambienteNfce($parametros);

        $attrs = [
            'empresa_id' => $empresa->id,
            'operacao' => $operacao,
            'modelo' => '65',
            'serie' => (string) $response->serie,
            'numero' => $response->numero,
            'cnf' => str_pad((string) $response->cNf, 8, '0', STR_PAD_LEFT),
            'chave' => $response->chave,
            'protocolo' => null,
            'status' => PdvVendaNfce::STATUS_CONTINGENCIA,
            'ambiente' => $ambiente,
            'tipo_emissao' => '9',
            'simulada' => false,
            'qr_code_conteudo' => $response->qrCodeUrl,
            'xml' => $response->xml,
            'motivo_rejeicao' => null,
            'motivo_contingencia' => $motivoContingencia ?? $justificativa,
            'autorizada_em' => $venda->fechado_em ?? now(),
        ];

        $nfce = $this->gravarRegistroFiscal($registro, $venda, $attrs, 'Convertida em contingência offline');

        (new ContadorCloudPortalHookService())->onNfceContingencia($nfce, $empresa);

        return $nfce;
    }

    public function emitirComNumero(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        int $numeroNfce,
        ?int $cNfFixo = null,
        ?string $justificativaContingencia = null,
        ?int $serieNfce = null,
    ): EmitirNfceResponse {
        CaBundleResolver::setProjectRoot(base_path());

        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            $operacao,
            $numeroNfce,
            $cNfFixo,
            $justificativaContingencia,
            serieNfce: $serieNfce,
        );

        return $this->engine->emitirNfce($request);
    }

    /**
     * Transmite a contingência usando o XML assinado gravado (o mesmo do DANFE impresso, com digVal
     * no QR Code). Só remonta quando o registro não tem XML de contingência.
     */
    public function autorizarContingencia(
        PdvVendaNfce $nfce,
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
    ): EmitirNfceResponse {
        CaBundleResolver::setProjectRoot(base_path());

        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);
        $nfeGravada = NfceXmlProtocolo::nfe($nfce->xml);

        if ($nfeGravada !== null) {
            $inconsistencia = NfceContingenciaConsistencia::motivo($nfce);

            if ($inconsistencia !== null) {
                throw new FiscalEngineException(
                    'Contingência inconsistente: '.$inconsistencia.'. Transmissão bloqueada para não divergir do DANFE emitido; nada foi enviado.'
                );
            }

            return $this->engine->autorizarNfceAssinada(
                nfeXml: $nfeGravada,
                certificate: NfceFiscalCertificateResolver::resolve($empresa, $parametros),
                tpAmb: $tpAmb,
                chave: (string) $nfce->chave,
                qrCodeUrl: (string) $nfce->qr_code_conteudo,
                numero: (int) $nfce->numero,
                serie: self::serieInt($nfce),
                cNf: (int) ltrim((string) $nfce->cnf, '0'),
            );
        }

        $cNf = (int) ltrim((string) $nfce->cnf, '0');
        $justificativa = NfceContingenciaJustificativa::normalize((string) $nfce->motivo_contingencia);
        $dataContingencia = $nfce->autorizada_em ?? $venda->fechado_em ?? now();

        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
            (int) $nfce->numero,
            $cNf,
            $justificativa,
            $dataContingencia,
            serieNfce: self::serieInt($nfce),
        );

        $prepared = $this->engine->prepararNfceAssinada($request);

        if (filled($nfce->chave) && $prepared['chave'] !== (string) $nfce->chave) {
            throw new FiscalEngineException(
                'Contingência sem XML gravado: a remontagem gerou chave '.$prepared['chave'].' diferente da chave emitida '
                .$nfce->chave.'. Transmissão bloqueada; nada foi enviado.'
            );
        }

        return $this->engine->autorizarNfceAssinada(
            nfeXml: $prepared['nfeXml'],
            certificate: $request->certificate,
            tpAmb: $tpAmb,
            chave: $prepared['chave'],
            qrCodeUrl: $prepared['qrUrl'],
            numero: $request->ide->numero,
            serie: $request->ide->serie,
            cNf: $request->ide->cNf,
        );
    }

    /**
     * Consulta a chave sem propagar falha de comunicação (null = situação desconhecida).
     */
    public function consultarSemFalhar(
        ?string $chave,
        Empresa $empresa,
        VendasParametro $parametros,
        ?Certificate $certificate = null,
    ): ?ConsultarNfceResponse {
        if (blank($chave)) {
            return null;
        }

        try {
            return $this->consultaService->consultarChave((string) $chave, $empresa, $parametros, $certificate);
        } catch (Throwable) {
            return null;
        }
    }

    public function operacaoSuportaEmissaoReal(string $operacao): bool
    {
        return in_array($operacao, [
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
            PdvFinalizarOperacao::FINALIZAR,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
        ], true);
    }

    public static function serieInt(PdvVendaNfce $nfce): int
    {
        return ((int) ltrim((string) ($nfce->serie ?: '1'), '0')) ?: 1;
    }

    /**
     * Registro existente que pode receber nova emissão (só simulada). Rejeitada/duplicidade/pendente
     * são resolvidas na tela NFC-e (F4/F5), que preserva a tentativa anterior.
     */
    private function registroReaproveitavel(PdvVenda $venda): ?PdvVendaNfce
    {
        $existente = PdvVendaNfce::query()->where('pdv_venda_id', $venda->id)->first();

        if ($existente === null) {
            return null;
        }

        if ($existente->simulada || (string) $existente->status === PdvVendaNfce::STATUS_SIMULADA) {
            return $existente;
        }

        throw new FiscalEngineException(
            'Esta venda já possui NFC-e ('.$existente->status.') nº '.($existente->numero ?: '—')
            .'. Consulte (F4) ou transmita (F5) na tela NFC-e; nova emissão bloqueada.'
        );
    }

    /**
     * @param  array{nfeXml: string, chave: string, qrUrl: string, enviNfe: string}  $prepared
     */
    private function gravarAntesDoEnvio(
        ?PdvVendaNfce $registro,
        PdvVenda $venda,
        Empresa $empresa,
        string $operacao,
        EmitirNfceRequest $request,
        array $prepared,
        VendasParametro $parametros,
        ?CarbonInterface $dataEmissao,
    ): PdvVendaNfce {
        $attrs = [
            'empresa_id' => $empresa->id,
            'operacao' => $operacao,
            'modelo' => '65',
            'serie' => (string) $request->ide->serie,
            'numero' => $request->ide->numero,
            'cnf' => str_pad((string) $request->ide->cNf, 8, '0', STR_PAD_LEFT),
            'chave' => $prepared['chave'],
            'protocolo' => null,
            'status' => PdvVendaNfce::STATUS_PENDENTE,
            'ambiente' => NfceFiscalCertificateResolver::ambienteNfce($parametros),
            'tipo_emissao' => '1',
            'simulada' => false,
            'qr_code_conteudo' => $prepared['qrUrl'],
            'xml' => $prepared['nfeXml'],
            'motivo_rejeicao' => 'Enviada à SEFAZ em '.now()->format('d/m/Y H:i:s').' — aguardando retorno.',
            'motivo_contingencia' => null,
            'autorizada_em' => $dataEmissao ?? $venda->fechado_em ?? now(),
        ];

        return $this->gravarRegistroFiscal($registro, $venda, $attrs, 'Substituída por nova tentativa de envio');
    }

    /**
     * Grava número/chave/XML com a numeração conferida no livro (número de outra NFC-e bloqueia)
     * e a tentativa anterior arquivada.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function gravarRegistroFiscal(?PdvVendaNfce $registro, PdvVenda $venda, array $attrs, string $motivoArquivo): PdvVendaNfce
    {
        NfceNumeracao::assegurarLivre(
            (int) $attrs['empresa_id'],
            $attrs['serie'],
            (int) $attrs['ambiente'],
            (int) $attrs['numero'],
            $registro?->id !== null ? (int) $registro->id : null,
        );

        if ($registro !== null) {
            $this->arquivarSeSubstituida($registro, $attrs, $motivoArquivo);
            $registro->update($attrs);
            $nfce = $registro->fresh() ?? $registro;
        } else {
            $nfce = PdvVendaNfce::query()->create($attrs + ['pdv_venda_id' => $venda->id]);
        }

        $conflito = NfceNumeracao::vincular($nfce);

        if ($conflito !== null) {
            $this->marcar($nfce, PdvVendaNfce::STATUS_REJEITADA, 'Número '.$nfce->numero.' já registrado para outra NFC-e (id '.$conflito.'). Não enviada à SEFAZ.');

            throw new FiscalEngineException('NFC-e nº '.$nfce->numero.' já pertence a outro documento no livro de numeração. Não enviada à SEFAZ; transmita (F5) para emitir com novo número.');
        }

        return $nfce;
    }

    /**
     * Arquiva a tentativa fiscal (chave/XML) antes de o registro receber outra chave ou outro XML.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function arquivarSeSubstituida(PdvVendaNfce $registro, array $attrs, string $motivo): void
    {
        if ($registro->simulada || (string) $registro->getRawOriginal('status') === PdvVendaNfce::STATUS_SIMULADA) {
            return;
        }

        $chaveAtual = (string) $registro->getRawOriginal('chave');
        $xmlAtual = (string) $registro->getRawOriginal('xml');

        if ($chaveAtual === '' && $xmlAtual === '') {
            return;
        }

        if ($chaveAtual === (string) ($attrs['chave'] ?? '') && $xmlAtual === (string) ($attrs['xml'] ?? '')) {
            return;
        }

        NfceTentativaHistorico::arquivar(
            $registro,
            $motivo.($chaveAtual !== (string) ($attrs['chave'] ?? '') ? ' (nova chave '.($attrs['chave'] ?? '—').')' : ' (mesma chave, XML remontado)'),
        );
    }

    private function marcarAutorizada(PdvVendaNfce $nfce, Empresa $empresa, EmitirNfceResponse $response): PdvVendaNfce
    {
        $nfce->update([
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'protocolo' => $response->protocolo,
            'xml' => $response->xml,
            'motivo_rejeicao' => null,
        ]);

        $nfce = $nfce->fresh() ?? $nfce;

        (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);

        return $nfce;
    }

    private function tratarFalhaEnvio(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        Certificate $certificate,
        FiscalEngineException $exception,
        bool $permitirContingencia,
    ): PdvVendaNfce {
        if (filled($exception->sefazCodigo)) {
            return $this->tratarRejeicao($nfce, $empresa, $parametros, $certificate, $exception);
        }

        $falha = trim($exception->getMessage());

        if (NfceFiscalComunicacao::naoEnviada($exception)) {
            if ($permitirContingencia) {
                return $this->converterEmContingencia($nfce, $empresa, $parametros, 'SEFAZ indisponível: '.$falha);
            }

            $this->marcar($nfce, PdvVendaNfce::STATUS_PENDENTE, 'SEFAZ indisponível, NFC-e não enviada ('.$falha.'). Transmita novamente (F5).');

            throw $exception;
        }

        // Timeout/resposta inválida: a SEFAZ pode ter autorizado. Consulta antes de decidir.
        $consulta = $this->consultarSemFalhar($nfce->chave, $empresa, $parametros, $certificate);

        if ($consulta !== null && ($consulta->autorizada || $consulta->cancelada || $consulta->denegada)) {
            $nfce = $this->consultaService->aplicarResultado($nfce, $empresa, $consulta);

            if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
                return $nfce;
            }

            throw new FiscalEngineException(
                'NFC-e consta na SEFAZ como '.$nfce->status.'. '.trim((string) $nfce->motivo_rejeicao)
            );
        }

        if ($consulta !== null && $consulta->statusCodigo === PdvNfceConsultaService::CSTAT_NAO_CONSTA) {
            if ($permitirContingencia) {
                return $this->converterEmContingencia(
                    $nfce,
                    $empresa,
                    $parametros,
                    'SEFAZ sem retorno ('.$falha.'); nota não recebida (cStat 217).',
                );
            }

            $this->marcar($nfce, PdvVendaNfce::STATUS_PENDENTE, 'SEFAZ sem retorno e a nota não consta na consulta (cStat 217). Transmita novamente (F5).');

            throw $exception;
        }

        $mensagem = 'Situação da NFC-e desconhecida na SEFAZ ('.$falha.'). A nota ficou em Gravados com a mesma chave: '
            .'consulte (F4) ou transmita (F5) para confirmar antes de qualquer nova emissão.';

        $this->marcar($nfce, PdvVendaNfce::STATUS_PENDENTE, $mensagem);

        throw new FiscalEngineException($mensagem);
    }

    /**
     * 204: a própria chave já foi recebida — recupera o protocolo, nunca marca rejeitada.
     * 539: número já usado com outra chave — a tentativa fica preservada em Duplicidade; nova
     * numeração só quando o conflito é confirmado (ver avaliarConflito539).
     */
    private function tratarRejeicao(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        Certificate $certificate,
        FiscalEngineException $exception,
    ): PdvVendaNfce {
        $codigo = (string) $exception->sefazCodigo;
        $motivo = $this->motivoRejeicao($exception);
        $chaveCitada = NfceXmlProtocolo::chaveCitada($exception->getMessage().' '.$exception->sefazMotivo);

        if ($codigo === '204' || ($codigo === '539' && $chaveCitada === (string) $nfce->chave)) {
            $consulta = $this->consultarSemFalhar($nfce->chave, $empresa, $parametros, $certificate);

            if ($consulta !== null && $consulta->autorizada) {
                $nfce = $this->consultaService->aplicarResultado($nfce, $empresa, $consulta);

                if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
                    return $nfce;
                }
            }

            $mensagem = 'A SEFAZ já recebeu esta chave (cStat '.$codigo.'), mas o protocolo não foi obtido agora. '
                .'A nota ficou em Gravados: use F4 Recuperar; não emita outra nota.';
            $this->marcar($nfce, PdvVendaNfce::STATUS_PENDENTE, $mensagem);

            throw new FiscalEngineException($mensagem, '204', $exception->sefazMotivo);
        }

        if ($codigo === '539') {
            $conflitante = NfceXmlProtocolo::chaveConflitante(
                $exception->getMessage().' '.$exception->sefazMotivo,
                (string) $nfce->chave,
            );
            $avaliacao = $this->avaliarConflito539($nfce, $conflitante, $empresa, $parametros, $certificate);

            if ($avaliacao['nfce'] !== null) {
                return $avaliacao['nfce'];
            }

            $this->marcar($nfce, PdvVendaNfce::STATUS_DUPLICIDADE, $this->motivo539($motivo, $conflitante, $avaliacao));

            throw $exception;
        }

        $this->marcar(
            $nfce,
            in_array($codigo, PdvVendaNfce::CSTAT_DENEGACAO, true) ? PdvVendaNfce::STATUS_DENEGADA : PdvVendaNfce::STATUS_REJEITADA,
            $motivo,
        );

        throw $exception;
    }

    /**
     * Rejeição 539 (número já usado com outra chave). Conflito confirmado = a chave citada pertence a
     * outra NFC-e do ERP, ou consta na SEFAZ e comprovadamente não é tentativa desta venda.
     * Quando a chave citada é tentativa anterior desta própria venda (histórico arquivado ou XML
     * reconstruído com o mesmo digVal), ela é restaurada como a NFC-e da venda — nunca renumera.
     *
     * @return array{confirmado: bool|null, detalhe: string, nfce: PdvVendaNfce|null}
     */
    public function avaliarConflito539(
        PdvVendaNfce $nfce,
        ?string $chaveConflitante,
        Empresa $empresa,
        VendasParametro $parametros,
        ?Certificate $certificate = null,
    ): array {
        $resultado = fn (?bool $confirmado, string $detalhe, ?PdvVendaNfce $restaurada = null): array => [
            'confirmado' => $confirmado,
            'detalhe' => $detalhe,
            'nfce' => $restaurada,
        ];

        if ($chaveConflitante === null) {
            return $resultado(null, 'a SEFAZ não informou a chave conflitante');
        }

        if ($chaveConflitante === (string) $nfce->chave) {
            return $resultado(false, 'a chave citada é a da própria nota');
        }

        if ((int) substr($chaveConflitante, 25, 9) !== (int) $nfce->numero
            || (int) substr($chaveConflitante, 22, 3) !== self::serieInt($nfce)) {
            return $resultado(null, 'a chave citada '.$chaveConflitante.' não corresponde à série/número desta nota');
        }

        $local = PdvVendaNfce::query()
            ->where('chave', $chaveConflitante)
            ->whereKeyNot($nfce->id)
            ->first(['id', 'pdv_venda_id', 'status']);

        if ($local !== null) {
            return $resultado(true, 'número já usado pela NFC-e da venda PDV #'.$local->pdv_venda_id.' ('.$local->status.')');
        }

        $consulta = $this->consultarSemFalhar($chaveConflitante, $empresa, $parametros, $certificate);

        if ($consulta === null) {
            return $resultado(null, 'a consulta da chave '.$chaveConflitante.' não obteve resposta da SEFAZ');
        }

        if ($consulta->statusCodigo === PdvNfceConsultaService::CSTAT_NAO_CONSTA) {
            return $resultado(false, 'a chave '.$chaveConflitante.' não consta na SEFAZ (cStat 217)');
        }

        if (! $consulta->autorizada && ! $consulta->cancelada && ! $consulta->denegada) {
            return $resultado(null, 'consulta da chave '.$chaveConflitante.' inconclusiva (cStat '.$consulta->statusCodigo.')');
        }

        $tentativa = NfceTentativaHistorico::localizar($nfce, $chaveConflitante);

        if ($tentativa !== null) {
            $nfeArquivada = NfceXmlProtocolo::nfe((string) $tentativa['xml']);
            $restaurada = $nfeArquivada !== null
                ? $this->restaurarTentativa($nfce, $chaveConflitante, $nfeArquivada, (string) ($tentativa['qr_code_conteudo'] ?? ''), $consulta, $empresa, 'histórico arquivado')
                : null;

            return $restaurada !== null
                ? $resultado(false, 'a chave '.$chaveConflitante.' é a tentativa original desta venda, restaurada', $restaurada)
                : $resultado(null, 'a chave '.$chaveConflitante.' é tentativa anterior desta venda, mas o XML arquivado não confere com o protocolo da SEFAZ');
        }

        $reconstruida = $this->reconstruirTentativa($nfce, $chaveConflitante, $empresa, $parametros);

        if ($reconstruida !== null) {
            $restaurada = $this->restaurarTentativa($nfce, $chaveConflitante, $reconstruida['nfeXml'], $reconstruida['qrUrl'], $consulta, $empresa, 'XML reconstruído com o mesmo digVal');

            if ($restaurada !== null) {
                return $resultado(false, 'a chave '.$chaveConflitante.' é a tentativa original desta venda, restaurada', $restaurada);
            }
        }

        return $resultado(
            true,
            'a chave '.$chaveConflitante.' consta na SEFAZ (cStat '.$consulta->statusCodigo.') com conteúdo diferente desta venda e não pertence a outra NFC-e do ERP',
        );
    }

    /**
     * Motivo da 539 com a chave conflitante e o resultado da verificação (substitui verificação anterior).
     *
     * @param  array{confirmado: bool|null, detalhe: string}  $avaliacao
     */
    public function motivo539(string $motivoBase, ?string $chaveConflitante, array $avaliacao): string
    {
        $base = trim(explode(' — conflito ', $motivoBase)[0]);

        if ($chaveConflitante !== null && ! str_contains($base, $chaveConflitante)) {
            $base .= ' [chave conflitante '.$chaveConflitante.']';
        }

        $prefixo = ' — conflito '.($avaliacao['confirmado'] === true ? 'confirmado' : 'não confirmado')
            .' ('.now()->format('d/m/Y H:i').'): '.$avaliacao['detalhe'].'. ';

        return $base.$prefixo.match ($avaliacao['confirmado']) {
            true => 'Tentativa preservada; F5 emitirá com novo número.',
            false => 'F5 reenviará com o mesmo número.',
            default => 'Número mantido; F5 verifica novamente antes de qualquer renumeração.',
        };
    }

    /**
     * Tentativa anterior desta venda autorizada na SEFAZ: volta a ser o documento do registro
     * (a tentativa rejeitada é arquivada). Só com digVal do protocolo igual ao DigestValue do XML.
     */
    private function restaurarTentativa(
        PdvVendaNfce $nfce,
        string $chave,
        string $nfeXml,
        string $qrCodeUrl,
        ConsultarNfceResponse $consulta,
        Empresa $empresa,
        string $origem,
    ): ?PdvVendaNfce {
        $protNFe = NfceXmlProtocolo::protNFe($consulta->xml);

        if ($protNFe === null
            || NfceXmlProtocolo::chaveDoProtocolo($protNFe) !== $chave
            || ! str_contains($nfeXml, 'Id="NFe'.$chave.'"')
            || ! NfceXmlProtocolo::digestConfere($nfeXml, $protNFe)) {
            return null;
        }

        NfceTentativaHistorico::arquivar(
            $nfce,
            'Tentativa rejeitada (539) substituída pela tentativa original '.$chave.' encontrada na SEFAZ ('.$origem.')',
        );

        $nfce->update([
            'serie' => (string) ((int) substr($chave, 22, 3)),
            'numero' => (int) substr($chave, 25, 9),
            'cnf' => substr($chave, 35, 8),
            'chave' => $chave,
            'tipo_emissao' => $chave[34],
            'qr_code_conteudo' => $qrCodeUrl !== '' ? $qrCodeUrl : NfceXmlProtocolo::qrCodeDoXml($nfeXml),
            'xml' => $nfeXml,
            'protocolo' => null,
            'status' => PdvVendaNfce::STATUS_PENDENTE,
            'motivo_rejeicao' => 'Tentativa original '.$chave.' recuperada após rejeição 539 ('.$origem.').',
        ]);

        $nfce = $this->consultaService->aplicarResultado($nfce->fresh() ?? $nfce, $empresa, $consulta);
        NfceNumeracao::vincular($nfce);

        return $nfce;
    }

    /**
     * Remonta o XML desta venda com número/série/cNF da chave citada (emissão normal). Mesma chave
     * e, depois, mesmo digVal do protocolo comprovam que a chave é tentativa desta venda.
     *
     * @return array{nfeXml: string, chave: string, qrUrl: string, enviNfe: string}|null
     */
    private function reconstruirTentativa(
        PdvVendaNfce $nfce,
        string $chave,
        Empresa $empresa,
        VendasParametro $parametros,
    ): ?array {
        if ($chave[34] !== '1') {
            return null;
        }

        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            return null;
        }

        try {
            $request = $this->payloadBuilder->build(
                $venda,
                $empresa,
                $parametros,
                (string) ($nfce->operacao ?: PdvFinalizarOperacao::NFCE_TRANSMITIR),
                (int) substr($chave, 25, 9),
                (int) substr($chave, 35, 8),
                serieNfce: (int) substr($chave, 22, 3),
            );
            $prepared = $this->engine->prepararNfceAssinada($request);
        } catch (Throwable) {
            return null;
        }

        return $prepared['chave'] === $chave ? $prepared : null;
    }

    private function converterEmContingencia(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        string $motivo,
    ): PdvVendaNfce {
        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e sem venda vinculada para contingência.');
        }

        return $this->emitirContingencia(
            venda: $venda,
            empresa: $empresa,
            parametros: $parametros,
            operacao: (string) $nfce->operacao,
            numeroNfce: (int) $nfce->numero,
            motivoContingencia: NfceContingenciaJustificativa::normalize($motivo),
            registro: $nfce,
            serieNfce: self::serieInt($nfce),
        );
    }

    private function marcar(PdvVendaNfce $nfce, string $status, string $motivo): void
    {
        $nfce->update([
            'status' => $status,
            'motivo_rejeicao' => mb_substr(trim($motivo), 0, 2000, 'UTF-8'),
        ]);
    }

    private function motivoRejeicao(FiscalEngineException $exception): string
    {
        return trim(
            ($exception->sefazCodigo !== null && $exception->sefazCodigo !== ''
                ? 'cStat '.$exception->sefazCodigo.': '
                : '')
            .($exception->sefazMotivo ?? $exception->getMessage()),
        );
    }
}
