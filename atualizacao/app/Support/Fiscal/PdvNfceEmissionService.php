<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Unitec\FiscalEngine\Dto\EmitirNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class PdvNfceEmissionService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function emitir(PdvVenda $venda, Empresa $empresa, VendasParametro $parametros, string $operacao): PdvVendaNfce
    {
        CaBundleResolver::setProjectRoot(base_path());

        $numeroNfce = $parametros->consumeNumero();

        try {
            $response = $this->emitirComNumero($venda, $empresa, $parametros, $operacao, $numeroNfce);

            return $this->persistirAutorizada($venda, $empresa, $operacao, $response, $parametros);
        } catch (FiscalEngineException $exception) {
            if (! NfceFiscalComunicacao::isIndisponivel($exception)) {
                throw $exception;
            }

            return $this->emitirContingencia(
                venda: $venda,
                empresa: $empresa,
                parametros: $parametros,
                operacao: $operacao,
                numeroNfce: $numeroNfce,
                motivoContingencia: NfceContingenciaJustificativa::normalize(
                    'SEFAZ indisponível: ' . trim($exception->getMessage()),
                ),
            );
        }
    }

    public function emitirContingencia(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        ?int $numeroNfce = null,
        ?string $motivoContingencia = null,
    ): PdvVendaNfce {
        CaBundleResolver::setProjectRoot(base_path());

        $numeroNfce ??= $parametros->consumeNumero();
        $justificativa = NfceContingenciaJustificativa::normalize($motivoContingencia);
        $request = $this->payloadBuilder->build(
            $venda,
            $empresa,
            $parametros,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
            $numeroNfce,
            justificativaContingencia: $justificativa,
        );
        $response = $this->engine->prepararNfceContingencia($request);
        $ambiente = NfceFiscalCertificateResolver::ambienteNfce($parametros);

        return PdvVendaNfce::query()->create([
            'pdv_venda_id' => $venda->id,
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
            'motivo_contingencia' => $motivoContingencia ?? $justificativa,
            'autorizada_em' => $venda->fechado_em ?? now(),
        ]);
    }

    public function emitirComNumero(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        int $numeroNfce,
        ?int $cNfFixo = null,
        ?string $justificativaContingencia = null,
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
        );

        return $this->engine->emitirNfce($request);
    }

    public function autorizarContingencia(
        PdvVendaNfce $nfce,
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
    ): EmitirNfceResponse {
        CaBundleResolver::setProjectRoot(base_path());

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
        );

        $prepared = $this->engine->prepararNfceAssinada($request);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);

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

    private function persistirAutorizada(
        PdvVenda $venda,
        Empresa $empresa,
        string $operacao,
        EmitirNfceResponse $response,
        VendasParametro $parametros,
    ): PdvVendaNfce {
        $ambiente = NfceFiscalCertificateResolver::ambienteNfce($parametros);

        $nfce = PdvVendaNfce::query()->create([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'operacao' => $operacao,
            'modelo' => '65',
            'serie' => (string) $response->serie,
            'numero' => $response->numero,
            'cnf' => str_pad((string) $response->cNf, 8, '0', STR_PAD_LEFT),
            'chave' => $response->chave,
            'protocolo' => $response->protocolo,
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'ambiente' => $ambiente,
            'tipo_emissao' => '1',
            'simulada' => false,
            'qr_code_conteudo' => $response->qrCodeUrl,
            'xml' => $response->xml,
            'autorizada_em' => $venda->fechado_em ?? now(),
        ]);

        (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);

        return $nfce;
    }

    public function operacaoSuportaEmissaoReal(string $operacao): bool
    {
        return in_array($operacao, [
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
            PdvFinalizarOperacao::FINALIZAR,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
        ], true);
    }
}
