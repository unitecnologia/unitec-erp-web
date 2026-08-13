<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Printing\Documents\NfceCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Illuminate\Support\Facades\Auth;

final class PdvNfceCupomPrinter
{
    public static function cupomUrl(int $pdvVendaId, bool $autoPrint = true, int $copias = 1): string
    {
        return route('erp.reports.nfce-cupom', [
            'venda' => $pdvVendaId,
            'auto' => $autoPrint ? 1 : 0,
            'copias' => max(1, min(3, $copias)),
        ]);
    }

    public static function livewireOpenJs(int $pdvVendaId, int $copias = 1): string
    {
        $venda = PdvVenda::query()->find($pdvVendaId);
        if (! $venda) {
            return '';
        }

        $user = Auth::user();
        $empresaId = session('erp_empresa_id', $user?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user?->empresa;

        $document = new NfceCupomPrintDocument(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) ($user?->name ?? 'OPERADOR'),
            operacao: (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR),
        );

        return PrintFacade::livewireOpenJs($document, $copias);
    }

    public static function protocoloCancelamentoUrl(int $pdvVendaId, bool $autoPrint = true): string
    {
        return route('erp.reports.nfce-cancelamento-protocolo', [
            'venda' => $pdvVendaId,
            'auto' => $autoPrint ? 1 : 0,
        ]);
    }

    public static function livewireOpenProtocoloCancelamentoJs(int $pdvVendaId): string
    {
        $url = self::protocoloCancelamentoUrl($pdvVendaId, true);
        $payload = json_encode(self::printPayload($url, 1), JSON_THROW_ON_ERROR);

        return '(function (payload) {
            if (window.ErpPrint?.openCupom) {
                window.ErpPrint.openCupom(payload);
                return;
            }
            window.open(payload.url, "_blank");
        })('.$payload.')';
    }

    /**
     * Payload HTML + opcional ESC/POS (Device Service) para cupom PDV / protocolo.
     *
     * @return array<string, mixed>
     */
    public static function printPayload(string $url, int $copias = 1, ?int $pdvVendaId = null): array
    {
        $target = PrintFacade::targetFromTerminal($copias);
        $useDevice = $target->preferredMode() === 'device'
            && $target->hasPrinter()
            && $pdvVendaId !== null
            && $pdvVendaId > 0;

        return [
            'document' => 'generic_html',
            'url' => $url,
            'mode' => $target->preferredMode(),
            'copias' => $target->copies,
            'printer' => $target->printerName,
            'tipo' => $target->tipoImpressora,
            'vendaId' => $pdvVendaId,
            'escposUrl' => $useDevice
                ? route('erp.print.pdv-escpos', [
                    'venda' => $pdvVendaId,
                    'copias' => $target->copies,
                ])
                : null,
        ];
    }

    public static function isNfceSimulada(?PdvVenda $venda): bool
    {
        if ($venda === null || ! $venda->fiscal) {
            return false;
        }

        $venda->loadMissing('nfce');

        return $venda->nfce === null || $venda->nfce->simulada;
    }
}
