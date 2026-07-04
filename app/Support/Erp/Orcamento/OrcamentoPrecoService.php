<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvProductPriceService;

final class OrcamentoPrecoService
{
    public function resolvePreco(Product $product, float $quantidade = 1, ?ProductGrade $grade = null): float
    {
        if ($grade !== null) {
            $precoGrade = (float) ($grade->preco ?? 0);

            if ($precoGrade > 0) {
                return round($precoGrade, 2);
            }
        }

        $priceService = new PdvProductPriceService(new PdvConfig);

        return $priceService->resolvePrecoVenda($product, $quantidade);
    }
}
