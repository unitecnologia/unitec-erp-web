<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Unitec\FiscalEngine\Dto\ConsultarNfceRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;

final class PdvNfceConsultaService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function recuperar(PdvVendaNfce $nfce, Empresa $empresa): PdvVendaNfce
    {
        if ($nfce->simulada) {
            throw new FiscalEngineException('Consulta SEFAZ não se aplica a NFC-e simulada.');
        }

        if (blank($nfce->chave)) {
            throw new FiscalEngineException('NFC-e sem chave de acesso para consulta.');
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Consulta real de NFC-e não está configurada para esta empresa/UF.');
        }

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);

        $response = $this->engine->consultarNfce(new ConsultarNfceRequest(
            certificate: $certificate,
            chave: (string) $nfce->chave,
            tpAmb: $tpAmb,
        ));

        $updates = [
            'motivo_rejeicao' => null,
        ];

        if ($response->autorizada) {
            $updates['status'] = PdvVendaNfce::STATUS_AUTORIZADA;
            $updates['protocolo'] = $response->protocolo !== '' ? $response->protocolo : $nfce->protocolo;
            $updates['autorizada_em'] = $nfce->autorizada_em ?? now();
        } elseif ($response->cancelada) {
            $updates['status'] = PdvVendaNfce::STATUS_CANCELADA;
            $updates['cancelada_em'] = $nfce->cancelada_em ?? now();
        } elseif ($response->denegada) {
            $updates['status'] = PdvVendaNfce::STATUS_REJEITADA;
            $updates['motivo_rejeicao'] = $response->statusMotivo;
        } elseif ($response->statusCodigo !== '' && $response->statusCodigo !== '100') {
            // Qualquer outro cStat relevante: marca rejeitada para não deixar
            // status antigo (pendente/contingência) incoerente com a SEFAZ.
            $updates['status'] = PdvVendaNfce::STATUS_REJEITADA;
            $updates['motivo_rejeicao'] = $response->statusMotivo !== ''
                ? $response->statusMotivo.' [cStat '.$response->statusCodigo.']'
                : 'Consulta SEFAZ retornou cStat '.$response->statusCodigo;
        }

        if (filled($response->xml) && $response->autorizada) {
            $updates['xml'] = $response->xml;
        }

        $nfce->update($updates);

        $nfce = $nfce->fresh() ?? $nfce;

        if ($response->autorizada) {
            (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);
        } elseif ($response->cancelada) {
            (new ContadorCloudPortalHookService())->onNfceCancelada($nfce, $empresa);
        }

        return $nfce;
    }
}
