<?php

namespace App\Support\Erp;

use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quantidade vendida por produto em janela recente.
 * Só vendas concluídas (fechado); canceladas ficam de fora.
 * Usado pelo ranking do PDV e pelo catálogo do Mesas.
 */
final class ProductSalesQty
{
    /** @var array<string, array<int, float>> memo por request (digitação PDV) */
    private static array $memoByIds = [];

    /**
     * @param  list<int>  $productIds
     * @return array<int, float> product_id => quantidade
     */
    public static function byProductIds(array $productIds, ?int $empresaId = null, int $dias = 90): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === [] || $dias < 1) {
            return [];
        }

        sort($productIds);
        $key = ($empresaId ?? 0).'|'.$dias.'|'.implode(',', $productIds);

        return self::$memoByIds[$key] ??= self::aggregate($empresaId, $dias, $productIds);
    }

    /**
     * Agrega vendas da empresa na janela (sem whereIn gigante).
     * Ideal para o catálogo Mesas: uma query, depois cruza em memória.
     *
     * @return array<int, float> product_id => quantidade
     */
    public static function byEmpresa(?int $empresaId, int $dias): array
    {
        if ($dias < 1) {
            return [];
        }

        return self::aggregate($empresaId, $dias, null);
    }

    /**
     * @param  list<int>|null  $productIds  null = todos os produtos com venda no período
     * @return array<int, float>
     */
    private static function aggregate(?int $empresaId, int $dias, ?array $productIds): array
    {
        $itens = (new VendaItem)->getTable();
        $vendas = (new Venda)->getTable();
        $itensPrefixed = DB::getTablePrefix().$itens;
        $from = now()->subDays($dias)->toDateString();

        $q = DB::table($itens)
            ->join($vendas, "{$vendas}.id", '=', "{$itens}.venda_id")
            ->where("{$vendas}.status", Venda::STATUS_FECHADO)
            ->where("{$vendas}.data", '>=', $from)
            ->whereNotNull("{$itens}.product_id")
            ->groupBy("{$itens}.product_id")
            // selectRaw NÃO aplica prefixo sozinho — usar nome físico da tabela.
            ->selectRaw("{$itensPrefixed}.product_id as product_id, SUM({$itensPrefixed}.quantidade) as qtd_saida");

        if ($empresaId && Schema::hasColumn($vendas, 'empresa_id')) {
            $q->where("{$vendas}.empresa_id", $empresaId);
        }

        if ($productIds !== null) {
            $q->whereIn("{$itens}.product_id", $productIds);
        }

        $map = [];
        foreach ($q->get() as $row) {
            $map[(int) $row->product_id] = (float) $row->qtd_saida;
        }

        return $map;
    }
}
