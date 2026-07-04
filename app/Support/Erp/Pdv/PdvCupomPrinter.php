<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;

final class PdvCupomPrinter
{
    public static function findPdvVendaIdForVenda(int $vendaId): ?int
    {
        $id = PdvVenda::query()
            ->where('venda_id', $vendaId)
            ->where('situacao', '!=', 'C')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function cupomUrl(int $pdvVendaId, bool $autoPrint = true): string
    {
        return route('erp.reports.pdv-cupom', [
            'venda' => $pdvVendaId,
            'auto' => $autoPrint ? 1 : 0,
        ]);
    }

    public static function livewireOpenJs(int $pdvVendaId, int $copias = 1): string
    {
        $venda = PdvVenda::query()->find($pdvVendaId);

        if (PdvNfceCupomPrinter::isNfceSimulada($venda)) {
            return PdvNfceCupomPrinter::livewireOpenJs($pdvVendaId, $copias);
        }

        $url = self::cupomUrl($pdvVendaId, true);
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
}
