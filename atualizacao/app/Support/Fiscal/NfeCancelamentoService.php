<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\Nfe\NfeEstoqueService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class NfeCancelamentoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function cancelar(Nfe $nfe, Empresa $empresa, string $justificativa): Nfe
    {
        if ($nfe->status === Nfe::STATUS_CANCELADA) {
            return $nfe;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            throw new FiscalEngineException('Somente NF-e transmitida pode ser cancelada.');
        }

        if (blank($nfe->chave) || blank($nfe->protocolo)) {
            throw new FiscalEngineException('NF-e sem chave ou protocolo de autorização para cancelamento.');
        }

        $justificativa = PdvEstornoMotivo::normalize($justificativa);
        $erroMotivo = PdvEstornoMotivo::validate($justificativa);

        if ($erroMotivo !== null) {
            throw new FiscalEngineException($erroMotivo);
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeCancelarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Cancelamento real de NF-e não está configurado para esta empresa/UF.');
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
            chave: (string) $nfe->chave,
            protocoloAutorizacao: (string) $nfe->protocolo,
            justificativa: $justificativa,
            tpAmb: $tpAmb,
        );

        $response = $this->engine->cancelarNfe($request);

        $nfe->update([
            'status' => Nfe::STATUS_CANCELADA,
            'situacao' => Nfe::SITUACAO_CANCELADA,
            'xml_cancelamento' => $response->xml,
            'protocolo_cancelamento' => $response->protocoloEvento !== '' ? $response->protocoloEvento : null,
        ]);

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_CANCELADA,
            titulo: 'NF-e cancelada',
            descricao: trim(
                'Justificativa: ' . $justificativa
                . (filled($response->protocoloEvento) ? '. Protocolo: ' . $response->protocoloEvento . '.' : '.'),
            ),
        );

        $nfe = $nfe->fresh() ?? $nfe;

        (new ContadorCloudPortalHookService())->onNfeCancelada($nfe, $empresa);

        (new NfeEstoqueService())->estornarSeAplicavel($nfe, $empresa);

        return $nfe->fresh() ?? $nfe;
    }
}
