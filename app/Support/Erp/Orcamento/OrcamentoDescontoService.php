<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Support\Facades\DB;

final class OrcamentoDescontoService
{
    public function ratearDesconto(Orcamento $orcamento): void
    {
        $orcamento->load('itens');

        if ($orcamento->itens->isEmpty()) {
            return;
        }

        $subtotal = (float) $orcamento->subtotal;
        $descontoTotal = (float) $orcamento->desconto_valor;

        if ($subtotal <= 0 || $descontoTotal <= 0) {
            $orcamento->itens()->update(['desconto' => 0]);

            return;
        }

        $soma = 0.0;
        $maiorItemId = null;
        $maiorTotal = -1.0;

        DB::transaction(function () use ($orcamento, $subtotal, $descontoTotal, &$soma, &$maiorItemId, &$maiorTotal): void {
            foreach ($orcamento->itens as $item) {
                $percentual = (float) $item->total / $subtotal;
                $valorDesconto = round($percentual * $descontoTotal, 2);
                $soma += $valorDesconto;

                $item->update(['desconto' => $valorDesconto]);

                if ((float) $item->total > $maiorTotal) {
                    $maiorTotal = (float) $item->total;
                    $maiorItemId = $item->id;
                }
            }

            $diferenca = round($descontoTotal - $soma, 2);

            if ($diferenca !== 0.0 && $maiorItemId !== null) {
                $maiorItem = OrcamentoItem::query()->find($maiorItemId);

                if ($maiorItem) {
                    $maiorItem->update([
                        'desconto' => round((float) $maiorItem->desconto + $diferenca, 2),
                    ]);
                }
            }
        });
    }
}
