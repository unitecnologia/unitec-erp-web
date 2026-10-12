<?php

namespace App\Support\Erp\Financeiro;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comissão adicional por item, usando o snapshot gravado em venda_itens.comissao_pct
 * na data da venda. Não lê products.comissao_pct atual — assim a Precificação
 * posterior não altera comissões históricas.
 *
 * Soma-se à comissão padrão do vendedor — não a substitui.
 */
final class ProdutoComissaoAdicional
{
    /**
     * @param  iterable<int|string>  $vendaIds
     * @return array<int, float> venda_id => adicional (2 casas)
     */
    public static function porVendaIds(iterable $vendaIds): array
    {
        $ids = [];
        foreach ($vendaIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []
            || ! Schema::hasTable('venda_itens')
            || ! Schema::hasColumn('venda_itens', 'comissao_pct')
        ) {
            return [];
        }

        $rows = DB::table('venda_itens')
            ->whereIn('venda_id', array_values($ids))
            ->where('comissao_pct', '>', 0)
            ->groupBy('venda_id')
            ->selectRaw('venda_id, COALESCE(SUM(total * comissao_pct / 100), 0) as adicional')
            ->pluck('adicional', 'venda_id');

        $out = [];
        foreach ($rows as $vendaId => $adicional) {
            $out[(int) $vendaId] = round((float) $adicional, 2);
        }

        return $out;
    }
}
