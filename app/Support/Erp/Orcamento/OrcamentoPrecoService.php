<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvProductPriceService;

final class OrcamentoPrecoService
{
    private ?PdvProductPriceService $pdvPrecos = null;

    public function resolvePreco(Product $product, float $quantidade = 1, ?ProductGrade $grade = null): float
    {
        if ($grade !== null) {
            $precoGrade = (float) ($grade->preco ?? 0);

            if ($precoGrade > 0) {
                return round($precoGrade, 2);
            }
        }

        return $this->pdvPrecos()->resolvePrecoVenda($product, $quantidade);
    }

    private function pdvPrecos(): PdvProductPriceService
    {
        return $this->pdvPrecos ??= new PdvProductPriceService(new PdvConfig);
    }
}
