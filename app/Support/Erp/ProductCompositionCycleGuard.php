<?php

namespace App\Support\Erp;

use App\Models\ProductComposition;

/**
 * Detecta ciclos em BOM (ex.: A→B→A) para cadastro e expansão de estoque.
 */
final class ProductCompositionCycleGuard
{
    /**
     * True se tornar $componentProductId componente de $kitProductId criaria ciclo.
     */
    public static function wouldCreateCycle(int $kitProductId, int $componentProductId): bool
    {
        if ($kitProductId <= 0 || $componentProductId <= 0) {
            return false;
        }

        if ($kitProductId === $componentProductId) {
            return true;
        }

        return self::reaches($componentProductId, $kitProductId, []);
    }

    /**
     * Percorre a árvore de composição a partir de $fromProductId.
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

        $componentIds = ProductComposition::query()
            ->where('product_id', $fromProductId)
            ->pluck('component_product_id');

        foreach ($componentIds as $componentId) {
            if (self::reaches((int) $componentId, $targetProductId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
