<?php

namespace App\Support\ForcaVendas;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use App\Models\Pedido;
use App\Models\PedidoItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Soma Desc./Acre./TT Bruto/TT Líquido do Monitor com as mesmas regras das células,
 * em uma passagem leve (sem N+1 de NF-e/venda).
 *
 * Otimização: 1 JOIN com SUM(desconto) dos itens por chunk (sem eager de coleções
 * de itens), preservando arredondamento por pedido via sumOrders().
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
        $pedidoTable = (new Pedido)->getTable();
        $itensTable = (new PedidoItem)->getTable();

        // Subconsulta só com os IDs filtrados: evita herdar withExists/eager loads
        // pesados da grade (NF-e, PIX, etc.) e recalcular 4 vezes no summarizer.
        $ids = (clone $query)->reorder()->pluck("{$table}.id");

        if ($ids->isEmpty()) {
            return [
                'desconto' => 0.0,
                'acrescimo' => 0.0,
                'bruto' => 0.0,
                'liquido' => 0.0,
                'qtd' => 0,
            ];
        }

        $desconto = 0.0;
        $acrescimo = 0.0;
        $bruto = 0.0;
        $liquido = 0.0;
        $qtd = 0;

        foreach ($ids->chunk(500) as $chunk) {
            $chunkIds = $chunk->values()->all();

            $descSub = DB::table($itensTable)
                ->select('pedido_id', DB::raw('COALESCE(SUM(desconto), 0) as soma_desconto_itens'))
                ->whereIn('pedido_id', function ($sub) use ($table, $chunkIds): void {
                    $sub->select('pedido_id')
                        ->from($table)
                        ->whereIn('id', $chunkIds)
                        ->whereNotNull('pedido_id');
                })
                ->groupBy('pedido_id');

            $rows = DB::table($table)
                ->leftJoin($pedidoTable, "{$pedidoTable}.id", '=', "{$table}.pedido_id")
                ->leftJoinSub($descSub, 'desc_itens', function ($join) use ($pedidoTable): void {
                    $join->on('desc_itens.pedido_id', '=', "{$pedidoTable}.id");
                })
                ->whereIn("{$table}.id", $chunkIds)
                ->select([
                    "{$table}.id",
                    "{$table}.total",
                    "{$table}.payload",
                    "{$table}.pedido_id",
                    "{$pedidoTable}.subtotal as pedido_subtotal",
                    "{$pedidoTable}.desconto_valor as pedido_desconto_valor",
                    'desc_itens.soma_desconto_itens',
                ])
                ->get();

            foreach ($rows as $row) {
                $order = self::hydrateOrderFromAggregateRow($row);
                $qtd++;
                // Mesma ordem do sumOrders: soma os valores já arredondados por pedido.
                $desconto += ForcaVendasMonitorResource::totalDesconto($order);
                $acrescimo += ForcaVendasMonitorResource::totalAcrescimo($order);
                $bruto += ForcaVendasMonitorResource::totalBruto($order);
                $liquido += (float) $order->total;
            }
        }

        return [
            'desconto' => round($desconto, 2),
            'acrescimo' => round($acrescimo, 2),
            'bruto' => round($bruto, 2),
            'liquido' => round($liquido, 2),
            'qtd' => $qtd,
        ];
    }

    /**
     * Reconstrói o grafo mínimo usado por totalDesconto/totalAcrescimo/totalBruto.
     */
    private static function hydrateOrderFromAggregateRow(object $row): ForcaVendasOrder
    {
        $payload = $row->payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($payload)) {
            $payload = [];
        }

        $order = new ForcaVendasOrder([
            'total' => $row->total,
            'payload' => $payload,
            'pedido_id' => $row->pedido_id,
        ]);
        $order->id = (int) $row->id;
        $order->exists = true;

        if ($row->pedido_id) {
            $pedido = new Pedido([
                'subtotal' => $row->pedido_subtotal,
                'desconto_valor' => $row->pedido_desconto_valor,
            ]);
            $pedido->id = (int) $row->pedido_id;
            $pedido->exists = true;

            $somaItens = round((float) ($row->soma_desconto_itens ?? 0), 2);
            // totalDesconto() usa sum('desconto') dos itens se > 0 — um item sintético basta.
            $itens = $somaItens > 0
                ? new EloquentCollection([new PedidoItem(['desconto' => $somaItens])])
                : new EloquentCollection;

            $pedido->setRelation('itens', $itens);
            $order->setRelation('pedido', $pedido);
        } else {
            $order->setRelation('pedido', null);
        }

        return $order;
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
