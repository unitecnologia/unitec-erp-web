<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvNfceTransmissaoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly PdvNfceEmissionService $emissionService = new PdvNfceEmissionService(),
    ) {}

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    public function transmitir(PdvVendaNfce $nfce, Empresa $empresa, ?callable $onProgress = null): PdvVendaNfce
    {
        if ($nfce->simulada) {
            throw new FiscalEngineException('NFC-e simulada não pode ser transmitida à SEFAZ.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Transmissão real de NFC-e não está configurada para esta empresa/UF.');
        }

        return match ($nfce->status) {
            PdvVendaNfce::STATUS_CONTINGENCIA => $this->transmitirContingencia($nfce, $empresa, $parametros, $onProgress),
            PdvVendaNfce::STATUS_PENDENTE, PdvVendaNfce::STATUS_REJEITADA => $this->emitirPendente($nfce, $empresa, $parametros, $onProgress),
            default => throw new FiscalEngineException('Somente NFC-e em contingência, gravada ou rejeitada pode ser transmitida.'),
        };
    }

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    private function transmitirContingencia(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        ?callable $onProgress = null,
    ): PdvVendaNfce {
        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e em contingência sem venda vinculada para transmissão.');
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_SEFAZ, 'nfce');

        $response = $this->emissionService->autorizarContingencia($nfce, $venda, $empresa, $parametros);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_AUTORIZACAO, 'nfce');

        $nfce->update([
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'protocolo' => $response->protocolo,
            'xml' => $response->xml,
            'qr_code_conteudo' => $response->qrCodeUrl,
            'tipo_emissao' => '9',
            'motivo_rejeicao' => null,
            'motivo_contingencia' => NfceContingenciaJustificativa::normalize((string) $nfce->motivo_contingencia),
            'autorizada_em' => now(),
        ]);

        $nfce = $nfce->fresh() ?? $nfce;

        (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);

        return $nfce;
    }

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    private function emitirPendente(
        PdvVendaNfce $nfce,
        Empresa $empresa,
        VendasParametro $parametros,
        ?callable $onProgress = null,
    ): PdvVendaNfce {
        $nfce->loadMissing('pdvVenda');
        $venda = $nfce->pdvVenda;

        if (! $venda instanceof PdvVenda) {
            throw new FiscalEngineException('NFC-e sem venda vinculada para transmissão.');
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR, 'nfce');
        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_SEFAZ, 'nfce');

        $operacao = (string) ($nfce->operacao ?: 'nfce_transmitir');
        $response = $this->emissionService->emitirComNumero(
            $venda,
            $empresa,
            $parametros,
            $operacao,
            (int) $nfce->numero,
            serieNfce: (int) ltrim((string) ($nfce->serie ?: '1'), '0') ?: 1,
        );

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_AUTORIZACAO, 'nfce');

        $nfce->update([
            'chave' => $response->chave,
            'protocolo' => $response->protocolo,
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'xml' => $response->xml,
            'qr_code_conteudo' => $response->qrCodeUrl,
            'cnf' => str_pad((string) $response->cNf, 8, '0', STR_PAD_LEFT),
            'tipo_emissao' => '1',
            'simulada' => false,
            'motivo_rejeicao' => null,
            'autorizada_em' => $venda->fechado_em ?? now(),
        ]);

        $nfce = $nfce->fresh() ?? $nfce;

        (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);

        return $nfce;
    }
}
