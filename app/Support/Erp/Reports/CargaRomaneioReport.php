<?php

namespace App\Support\Erp\Reports;

use App\Models\Carga;
use App\Models\VendaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CargaRomaneioReport
{
    public const ORD_PEDIDO = 'pedido';

    public const ORD_ALFABETICA = 'alfabetica';

    public const ORD_QUANTIDADE = 'quantidade';

    /**
     * @return array<string, string>
     */
    public static function ordenacaoLabels(): array
    {
        return [
            self::ORD_PEDIDO => 'Nº pedido',
            self::ORD_ALFABETICA => 'Ordem alfabética',
            self::ORD_QUANTIDADE => 'Quantidade',
        ];
    }

    public static function normalizeOrdenacao(?string $ordenacao): string
    {
        return array_key_exists((string) $ordenacao, self::ordenacaoLabels())
            ? (string) $ordenacao
            : self::ORD_QUANTIDADE;
    }

    /**
     * @return list<array{pedido: string, cliente: string, valor: float, quantidade: float}>
     */
    public static function buildPedidos(Carga $carga, string $ordenacao = self::ORD_PEDIDO): array
    {
        $ordenacao = self::normalizeOrdenacao($ordenacao);

        $rows = $carga->pedidos
            ->map(function ($pedido): array {
                $numero = ltrim((string) $pedido->numero, '0');

                return [
                    'pedido' => $numero !== '' ? $numero : '0',
                    'pedido_sort' => (int) preg_replace('/\D/', '', (string) $pedido->numero),
                    'cliente' => (string) ($pedido->cliente?->nome_razao ?: 'CONSUMIDOR'),
                    'valor' => (float) ($pedido->total ?? 0),
                    'quantidade' => (float) $pedido->itens->sum('quantidade'),
                ];
            });

        $sorted = match ($ordenacao) {
            self::ORD_ALFABETICA => $rows->sortBy('cliente', SORT_NATURAL | SORT_FLAG_CASE),
            self::ORD_QUANTIDADE => $rows->sortByDesc('quantidade'),
            default => $rows->sortBy('pedido_sort'),
        };

        return $sorted
            ->values()
            ->map(static function (array $row): array {
                unset($row['pedido_sort']);

                return $row;
            })
            ->all();
    }

    /**
     * @return list<array{codigo: string, produto: string, quantidade: float}>
     */
    public static function buildResumoProdutos(Carga $carga, string $ordenacao = self::ORD_ALFABETICA): array
    {
        $ordenacao = self::normalizeOrdenacao($ordenacao);
        $pedidoIds = $carga->pedidos->pluck('id')->all();

        if ($pedidoIds === []) {
            return [];
        }

        /** @var Collection<int, VendaItem> $itens */
        $itens = VendaItem::query()
            ->with('product:id,codigo,descricao,peso_kg')
            ->whereIn('venda_id', $pedidoIds)
            ->get();

        $grouped = $itens
            ->groupBy(fn (VendaItem $item): string => (string) ($item->product_id ?: ('x-'.$item->id)))
            ->map(function (Collection $group): array {
                /** @var VendaItem $first */
                $first = $group->first();
                $product = $first->product;

                return [
                    'codigo' => (string) ($product?->codigo ?: '—'),
                    'produto' => (string) ($product?->descricao ?: 'PRODUTO'),
                    'quantidade' => (float) $group->sum('quantidade'),
                ];
            });

        $sorted = match ($ordenacao) {
            self::ORD_QUANTIDADE => $grouped->sortByDesc('quantidade'),
            self::ORD_PEDIDO => $grouped->sortBy('codigo', SORT_NATURAL | SORT_FLAG_CASE),
            default => $grouped->sortBy('produto', SORT_NATURAL | SORT_FLAG_CASE),
        };

        return $sorted->values()->all();
    }

    public static function calcularPesoTotalKg(Carga $carga): float
    {
        $pedidoIds = $carga->pedidos->pluck('id')->all();

        if ($pedidoIds === []) {
            return 0.0;
        }

        $prefix = DB::connection()->getTablePrefix();
        $itens = $prefix.'venda_itens';
        $prods = $prefix.'products';

        return (float) VendaItem::query()
            ->join('products', 'products.id', '=', 'venda_itens.product_id')
            ->whereIn('venda_itens.venda_id', $pedidoIds)
            ->selectRaw("COALESCE(SUM(`{$itens}`.quantidade * COALESCE(`{$prods}`.peso_kg, 0)), 0) as peso")
            ->value('peso');
    }

    public static function formatPesoKg(float $value): string
    {
        return number_format($value, 3, ',', '.').' kg';
    }

    public static function formatMoney(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    public static function formatQuantidade(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
