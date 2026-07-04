<?php

namespace App\Support\Erp;

use App\Models\Product;

class ProductCloneService
{
    public function cloneFrom(Product $source): Product
    {
        $clone = $source->replicate([
            'foto_path',
            'created_at',
            'updated_at',
        ]);

        $clone->codigo = Product::nextCodigo();
        $clone->referencia = null;
        $clone->codigo_barras = null;
        $clone->codigo_barras_caixa = null;
        $clone->preco_compra = 0;
        $clone->preco_custo = 0;
        $clone->preco_venda = 0;
        $clone->preco_venda_prazo = 0;
        $clone->preco_atacado = 0;
        $clone->e_medio = 0;
        $clone->ult_compra = 0;
        $clone->ult_compra_anterior = 0;
        $clone->preco_custo_anterior = 0;
        $clone->preco_venda_anterior = 0;
        $clone->estoque = 0;
        $clone->estoque_inicial = 0;
        $clone->foto_path = null;
        $clone->save();

        return $clone;
    }
}
