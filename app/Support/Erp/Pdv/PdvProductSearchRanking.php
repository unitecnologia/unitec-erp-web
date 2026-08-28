<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranking da consulta PDV: começa-com o termo → qtd saída (90 dias) → descrição.
 *
 * Ordena em PHP após filtrar candidatos (evita join/subquery com prefixo unitec_
 * e agregação pesada a cada tecla).
 */
final class PdvProductSearchRanking
{
    public const DIAS_SAIDA = 90;

    public const CANDIDATE_LIMIT = 200;

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $prefixAttributes  Atributos do model (ex.: descricao, codigo)
     * @return Collection<int, Product>
     */
    public static function rankProducts(
        Collection $products,
        string $term,
        array $prefixAttributes,
        ?int $empresaId = null,
        int $limit = 100,
    ): Collection {
        if ($products->isEmpty()) {
            return $products;
        }

        $saidas = self::qtdSaidaByProductIds(
            $products->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $empresaId,
        );

        $prefix = mb_strtoupper($term, 'UTF-8');

        return $products
            ->sort(function (Product $a, Product $b) use ($prefix, $prefixAttributes, $saidas): int {
                $aStarts = self::startsWithAny($a, $prefix, $prefixAttributes) ? 0 : 1;
                $bStarts = self::startsWithAny($b, $prefix, $prefixAttributes) ? 0 : 1;

                if ($aStarts !== $bStarts) {
                    return $aStarts <=> $bStarts;
                }

                $aQty = $saidas[(int) $a->id] ?? 0.0;
                $bQty = $saidas[(int) $b->id] ?? 0.0;

                if ($aQty !== $bQty) {
                    return $bQty <=> $aQty;
                }

                return strcmp(
                    mb_strtoupper((string) $a->descricao, 'UTF-8'),
                    mb_strtoupper((string) $b->descricao, 'UTF-8'),
                );
            })
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<string>  $attributes
     */
    private static function startsWithAny(Product $product, string $prefix, array $attributes): bool
    {
        if ($prefix === '') {
            return false;
        }

        foreach ($attributes as $attribute) {
            $value = mb_strtoupper(trim((string) ($product->{$attribute} ?? '')), 'UTF-8');

            if ($value !== '' && str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, float> product_id => qtd
     */
    public static function qtdSaidaByProductIds(array $productIds, ?int $empresaId = null): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return [];
        }

        $itens = (new VendaItem)->getTable();
        $vendas = (new Venda)->getTable();
        $itensPrefixed = DB::getTablePrefix().$itens;
        $from = now()->subDays(self::DIAS_SAIDA)->toDateString();

        $q = DB::table($itens)
            ->join($vendas, "{$vendas}.id", '=', "{$itens}.venda_id")
            ->where("{$vendas}.status", Venda::STATUS_FECHADO)
            ->where("{$vendas}.data", '>=', $from)
            ->whereIn("{$itens}.product_id", $productIds)
            ->groupBy("{$itens}.product_id")
            // selectRaw NÃO aplica prefixo sozinho — usar nome físico da tabela.
            ->selectRaw("{$itensPrefixed}.product_id as product_id, SUM({$itensPrefixed}.quantidade) as qtd_saida");

        if ($empresaId && Schema::hasColumn($vendas, 'empresa_id')) {
            $q->where("{$vendas}.empresa_id", $empresaId);
        }

        $map = [];
        foreach ($q->get() as $row) {
            $map[(int) $row->product_id] = (float) $row->qtd_saida;
        }

        return $map;
    }
}
