<?php

namespace App\Support\ContadorCloud;

use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NotaFornecedor;
use App\Models\PdvVendaNfce;
use Illuminate\Support\Facades\Log;

final class ContadorCloudPortalHookService
{
    public function __construct(
        private readonly ContadorCloudDocumentPayloadBuilder $payloadBuilder = new ContadorCloudDocumentPayloadBuilder(),
        private readonly ContadorCloudSyncService $syncService = new ContadorCloudSyncService(),
    ) {}

    public function onNfeAutorizada(Nfe $nfe, Empresa $empresa): void
    {
        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromNfe($nfe, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
        );
    }

    public function onNfeCancelada(Nfe $nfe, Empresa $empresa): void
    {
        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromNfe($nfe, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO),
        );
    }

    public function onNfceAutorizada(PdvVendaNfce $nfce, Empresa $empresa): void
    {
        if ($nfce->simulada) {
            return;
        }

        $nfce->loadMissing('pdvVenda');

        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromNfce($nfce, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO),
        );
    }

    public function onNfceCancelada(PdvVendaNfce $nfce, Empresa $empresa): void
    {
        if ($nfce->simulada) {
            return;
        }

        $nfce->loadMissing('pdvVenda');

        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromNfce($nfce, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_CANCELADO),
        );
    }

    public function onNotaFornecedorImportada(
        NotaFornecedor $nota,
        Empresa $empresa,
        ?string $xml = null,
        bool $immediate = true,
    ): void {
        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromNotaFornecedor($nota, $empresa, $xml),
            $immediate,
        );
    }

    public function onCompraRegistrada(Compra $compra, Empresa $empresa): void
    {
        if (blank($compra->chave_nfe)) {
            return;
        }

        $this->safeDispatch(
            $empresa,
            $this->payloadBuilder->fromCompra($compra, $empresa),
        );
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function safeDispatch(Empresa $empresa, array $documento, bool $immediate = true): void
    {
        try {
            $this->syncService->dispatch($empresa, $documento, $immediate);
        } catch (\Throwable $exception) {
            Log::error('Portal do Contador: erro inesperado no envio.', [
                'empresa_id' => $empresa->id,
                'chave' => $documento['chave'] ?? null,
                'tipo' => $documento['tipo'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
