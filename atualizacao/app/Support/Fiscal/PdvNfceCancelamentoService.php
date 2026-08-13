<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class PdvNfceCancelamentoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function cancelar(PdvVenda $venda, Empresa $empresa, string $justificativa): PdvVendaNfce
    {
        $venda->loadMissing('nfce');
        $nfce = $venda->nfce;

        if (! $nfce) {
            throw new FiscalEngineException('NFC-e não encontrada para esta venda.');
        }

        if ($nfce->status === PdvVendaNfce::STATUS_CANCELADA) {
            return $nfce;
        }

        if ($nfce->simulada) {
            return $this->cancelarSimulada($nfce);
        }

        if ($nfce->status !== PdvVendaNfce::STATUS_AUTORIZADA) {
            throw new FiscalEngineException('Somente NFC-e autorizada pode ser cancelada.');
        }

        $justificativa = PdvEstornoMotivo::normalize($justificativa);
        $erroMotivo = PdvEstornoMotivo::validate($justificativa);

        if ($erroMotivo !== null) {
            throw new FiscalEngineException($erroMotivo);
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeCancelarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Cancelamento real de NFC-e não está configurado para esta empresa/UF.');
        }

        CaBundleResolver::setProjectRoot(base_path());

        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);
        $senha = $parametros->safeSenhaCertificado();

        if ($certPath === null || $senha === null) {
            throw new FiscalEngineException('Certificado digital ou senha não configurados.');
        }

        $certificate = CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);
        $tpAmb = $parametros->ambiente === VendasParametro::AMBIENTE_PRODUCAO ? 1 : 2;

        $request = new CancelarNfceRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            chave: (string) $nfce->chave,
            protocoloAutorizacao: (string) $nfce->protocolo,
            justificativa: $justificativa,
            tpAmb: $tpAmb,
        );

        $response = $this->engine->cancelarNfce($request);

        $nfce->update([
            'status' => PdvVendaNfce::STATUS_CANCELADA,
            'xml_cancelamento' => $response->xml,
            'protocolo_cancelamento' => $response->protocoloEvento !== '' ? $response->protocoloEvento : null,
            'cancelada_em' => now(),
        ]);

        $nfce = $nfce->fresh() ?? $nfce;

        (new ContadorCloudPortalHookService())->onNfceCancelada($nfce, $empresa);

        return $nfce;
    }

    private function cancelarSimulada(PdvVendaNfce $nfce): PdvVendaNfce
    {
        $nfce->update([
            'status' => PdvVendaNfce::STATUS_CANCELADA,
            'cancelada_em' => now(),
        ]);

        return $nfce->fresh() ?? $nfce;
    }
}
