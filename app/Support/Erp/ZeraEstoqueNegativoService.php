<?php

namespace App\Support\Erp;

use App\Models\Product;

final class ZeraEstoqueNegativoService
{
    public function countNegativos(): int
    {
        return Product::query()->where('estoque', '<', 0)->count();
    }

    /**
     * Zera o saldo de todos os produtos com estoque negativo.
     *
     * @return int Quantidade de produtos ajustados
     */
    public function zerarTodos(): int
    {
        $count = $this->countNegativos();

        if ($count === 0) {
            return 0;
        }

        Product::query()->where('estoque', '<', 0)->update(['estoque' => 0]);

        return $count;
    }
}
