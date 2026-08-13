<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Orcamento;

final class OrcamentoTotaisService
{
    public function recalcular(Orcamento $orcamento): void
    {
        $subtotal = (float) $orcamento->itens()->sum('total');
        $desconto = (float) $orcamento->desconto_valor;
        $total = round(max(0, $subtotal - $desconto), 2);

        $percentual = 0.0;

        if ($subtotal > 0 && $desconto > 0) {
            $percentual = round(100 - (($total * 100) / $subtotal), 2);
        }

        $orcamento->update([
            'subtotal' => round($subtotal, 2),
            'percentual_desconto' => $percentual,
            'total' => $total,
        ]);
    }
}
