<?php

namespace App\Support\Erp\Fabricacao;

use App\Models\ProductReceita;
use App\Models\ProductReceitaItem;
use App\Support\Erp\ErpSchema;

/**
 * Detecta ciclos em receitas de fabricação (ex.: A usa B e B usa A).
 * Independente de ProductComposition / kits de venda.
 */
final class ProductReceitaCycleGuard
{
    /**
     * True se tornar $componentProductId ingrediente de $acabadoProductId criaria ciclo.
     */
    public static function wouldCreateCycle(int $acabadoProductId, int $componentProductId): bool
    {
        if ($acabadoProductId <= 0 || $componentProductId <= 0) {
            return false;
        }

        if ($acabadoProductId === $componentProductId) {
            return true;
        }

        return self::reaches($componentProductId, $acabadoProductId, []);
    }

    /**
     * Percorre a árvore de receitas a partir de $fromProductId.
     * True se $targetProductId aparecer na cadeia.
     *
     * @param  array<int, true>  $visited
     */
    public static function reaches(int $fromProductId, int $targetProductId, array $visited = []): bool
    {
        if ($fromProductId <= 0) {
            return false;
        }

        if ($fromProductId === $targetProductId) {
            return true;
        }

        if (isset($visited[$fromProductId])) {
            return false;
        }

        $visited[$fromProductId] = true;

        if (! ErpSchema::hasTable('product_receitas') || ! ErpSchema::hasTable('product_receita_itens')) {
            return false;
        }

        $receitaId = ProductReceita::query()
            ->where('product_id', $fromProductId)
            ->where('ativo', true)
            ->value('id');

        if ($receitaId === null) {
            return false;
        }

        $componentIds = ProductReceitaItem::query()
            ->where('receita_id', (int) $receitaId)
            ->pluck('component_product_id');

        foreach ($componentIds as $componentId) {
            if (self::reaches((int) $componentId, $targetProductId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
