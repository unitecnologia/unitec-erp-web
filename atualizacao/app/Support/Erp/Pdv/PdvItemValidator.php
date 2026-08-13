<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;

final class PdvItemValidator
{
    public function __construct(
        private readonly PdvConfig $config,
        private readonly PdvProductPriceService $priceService,
    ) {}

    public function validaEstoque(Product $product, float $quantidade, ?int $productGradeId = null): ?string
    {
        if ($product->is_servico) {
            return null;
        }

        $stockService = new PdvStockService();

        if ($msg = $stockService->validaEstoqueGrade($product, $productGradeId, $quantidade)) {
            return $msg;
        }

        if ($product->is_composicao) {
            return $stockService->validaEstoqueComposicao($product, $quantidade);
        }

        if (! $this->config->bloquearEstoqueNegativo()) {
            return null;
        }

        $estoque = (float) $product->estoque;

        if ($estoque < 0) {
            return 'Estoque negativo!';
        }

        if ($estoque < $quantidade) {
            return 'Estoque insuficiente!';
        }

        return null;
    }

    public function validaQuantidade(float $quantidade): ?string
    {
        if ($quantidade <= 0) {
            return 'Quantidade inválida.';
        }

        if ($quantidade > 9999) {
            return 'Quantidade maior que o permitido.';
        }

        return null;
    }

    public function validaPreco(Product $product, float $preco, float $quantidade): ?string
    {
        if ($preco <= 0) {
            return 'Produto está com o preço inválido.';
        }

        return $this->priceService->validaPrecoInformado($product, $preco, $quantidade);
    }
}
