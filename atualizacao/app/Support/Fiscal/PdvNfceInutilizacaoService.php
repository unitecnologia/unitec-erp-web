<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;
use Unitec\FiscalEngine\Dto\InutilizarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;

final class PdvNfceInutilizacaoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function inutilizar(
        Empresa $empresa,
        int $serie,
        int $numeroInicial,
        int $numeroFinal,
        string $justificativa,
    ): InutilizarNfceResponse {
        $justificativa = PdvEstornoMotivo::normalize($justificativa);
        $erroMotivo = PdvEstornoMotivo::validate($justificativa);

        if ($erroMotivo !== null) {
            throw new FiscalEngineException($erroMotivo);
        }

        if ($numeroInicial < 1 || $numeroFinal < $numeroInicial) {
            throw new FiscalEngineException('Faixa de numeração inválida para inutilização.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Inutilização real de NFC-e não está configurada para esta empresa/UF.');
        }

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);

        return $this->engine->inutilizarNfce(new InutilizarNfceRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            tpAmb: $tpAmb,
            serie: $serie,
            numeroInicial: $numeroInicial,
            numeroFinal: $numeroFinal,
            justificativa: $justificativa,
        ));
    }
}
