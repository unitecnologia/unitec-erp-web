<?php

namespace App\Support\ForcaVendas;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Soma Desc./Acre./TT Bruto/TT Líquido do Monitor com as mesmas regras das células,
 * em uma passagem leve (sem N+1 de NF-e/venda).
 */
final class ForcaVendasMonitorTotaisAggregator
{
    /**
     * @return array{desconto: float, acrescimo: float, bruto: float, liquido: float, qtd: int}
     */
    public static function fromQuery(Builder $query): array
    {
        $model = $query->getModel();
        $table = $model->getTable();

        // Subconsulta só com os IDs filtrados: evita herdar withExists/eager loads
        // pesados da grade (NF-e, PIX, etc.) e recalcular 4 vezes no summarizer.
        $idsQuery = (clone $query)->reorder()->select("{$table}.id");

        $orders = $model->newQuery()
            ->whereIn("{$table}.id", $idsQuery)
            ->select([
                "{$table}.id",
                "{$table}.total",
                "{$table}.payload",
                "{$table}.pedido_id",
            ])
            ->with([
                // Constraint de eager load recebe a Relation (BelongsTo/HasMany), não o Builder.
                'pedido' => static fn ($q) => $q->select(['id', 'subtotal', 'desconto_valor']),
                'pedido.itens' => static fn ($q) => $q->select(['id', 'pedido_id', 'desconto']),
            ])
            ->lazyById(250);

        return self::sumOrders($orders);
    }

    /**
     * @param  iterable<ForcaVendasOrder>  $orders
     * @return array{desconto: float, acrescimo: float, bruto: float, liquido: float, qtd: int}
     */
    public static function sumOrders(iterable $orders): array
    {
        $desconto = 0.0;
        $acrescimo = 0.0;
        $bruto = 0.0;
        $liquido = 0.0;
        $qtd = 0;

        foreach ($orders as $order) {
            $qtd++;
            $desconto += ForcaVendasMonitorResource::totalDesconto($order);
            $acrescimo += ForcaVendasMonitorResource::totalAcrescimo($order);
            $bruto += ForcaVendasMonitorResource::totalBruto($order);
            $liquido += (float) $order->total;
        }

        return [
            'desconto' => round($desconto, 2),
            'acrescimo' => round($acrescimo, 2),
            'bruto' => round($bruto, 2),
            'liquido' => round($liquido, 2),
            'qtd' => $qtd,
        ];
    }
}
