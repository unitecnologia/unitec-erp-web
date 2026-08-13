<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\Fiscal\PdvNfceEmissionService;
use App\Support\Fiscal\PdvNfceFiscalPayloadBuilder;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvVendaNfceService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly PdvNfceEmissionService $emissionService = new PdvNfceEmissionService(),
    ) {}

    public function registrar(PdvVenda $venda, ?Empresa $empresa, string $operacao): PdvVendaNfce
    {
        if ($empresa === null) {
            throw new FiscalEngineException('Empresa não configurada para emissão fiscal.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->emissionService->operacaoSuportaEmissaoReal($operacao)
            || ! $this->payloadBuilder->podeEmitirReal($parametros, $empresa, $operacao)) {
            $motivos = $this->payloadBuilder->motivosBloqueioEmissaoReal($parametros, $empresa, $operacao);

            throw new FiscalEngineException(
                'NFC-e real não configurada. '.($motivos !== []
                    ? implode(' ', $motivos)
                    : 'Verifique certificado, CSC, responsável técnico e UF (SC).')
                .' Cupom simulado foi desativado.'
            );
        }

        if ($operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA) {
            return $this->emissionService->emitirContingencia($venda, $empresa, $parametros, $operacao);
        }

        return $this->emissionService->emitir($venda, $empresa, $parametros, $operacao);
    }
}
