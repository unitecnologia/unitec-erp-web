<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;

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
        $url = self::cupomUrl($pdvVendaId, true, $copias);
        $payload = json_encode(['url' => $url, 'copias' => max(1, $copias)], JSON_THROW_ON_ERROR);

        return '(function (payload) {
            if (window.ErpPdvPrint?.openCupom) {
                window.ErpPdvPrint.openCupom(payload);
                return;
            }
            window.open(payload.url, "_blank");
            if (payload.copias > 1) {
                window.setTimeout(function () {
                    window.open(payload.url.replace("auto=1", "auto=0"), "_blank");
                }, 800);
            }
        })(' . $payload . ')';
    }

    public static function isNfceSimulada(?PdvVenda $venda): bool
    {
        return $venda !== null && $venda->fiscal;
    }
}
