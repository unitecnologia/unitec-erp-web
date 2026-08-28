<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\VendasParametro;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\Nfe\NfeEstoqueService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Util\CaBundleResolver;

final class NfeEmissionService
{
    public function __construct(
        private readonly NfeFiscalPayloadBuilder $payloadBuilder = new NfeFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     */
    public function transmitir(Nfe $nfe, Empresa $empresa, ?callable $onProgress = null): Nfe
    {
        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            throw new FiscalEngineException('Somente NF-e aberta pode ser transmitida.');
        }

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_VALIDAR);

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeEmitirReal($parametros, $empresa)) {
            throw new FiscalEngineException('Transmissão real de NF-e não está configurada para esta empresa/UF.');
        }

        CaBundleResolver::setProjectRoot(base_path());

        (new NfeEstoqueService())->validarAntesDeTransmitir($nfe, $empresa);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_XML);

        $request = $this->payloadBuilder->build($nfe, $empresa, $parametros);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_ASSINAR);

        $prepared = $this->engine->prepararNfeAssinada($request);

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_SEFAZ);

        $response = $this->engine->autorizarNfeAssinada(
            nfeXml: $prepared['nfeXml'],
            certificate: $request->certificate,
            tpAmb: $request->ide->tpAmb,
            chave: $prepared['chave'],
            numero: $request->ide->numero,
            serie: $request->ide->serie,
            cNf: $request->ide->cNf,
        );

        FiscalTransmitProgress::report($onProgress, FiscalTransmitProgress::STEP_AUTORIZACAO);

        $nfe->update([
            'chave' => $response->chave,
            'cnf' => str_pad((string) $response->cNf, 8, '0', STR_PAD_LEFT),
            'protocolo' => $response->protocolo,
            'xml' => $response->xml,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'tipo_emissao' => '1',
        ]);

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_TRANSMITIDA,
            titulo: 'NF-e transmitida',
            descricao: trim('Protocolo: ' . $response->protocolo . '. Chave: ' . $response->chave . '.'),
        );

        $nfe = $nfe->fresh() ?? $nfe;

        (new ContadorCloudPortalHookService())->onNfeAutorizada($nfe, $empresa);

        (new NfeEstoqueService())->baixarSeAplicavel($nfe, $empresa);

        return $nfe->fresh() ?? $nfe;
    }
}
