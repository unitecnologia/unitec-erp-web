<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;

final class PdvScaleBarcodeService
{
    public function __construct(
        private readonly PdvConfig $config,
        private readonly PdvProductPriceService $priceService,
    ) {}

    /**
     * @return array{quantidade: float, preco: float, total: float}|null
     */
    public function parse(Product $product, string $barcode): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '' || strlen($barcode) < 7) {
            return null;
        }

        $prefixoProduto = trim((string) ($product->prefixo_balanca ?? ''));

        if ($prefixoProduto === '') {
            return null;
        }

        if (! str_starts_with($barcode, $prefixoProduto)) {
            return null;
        }

        $preco = $this->priceService->resolvePrecoVenda($product, 1);

        if ($preco <= 0) {
            return null;
        }

        $modelo = $this->config->modeloBalanca();
        $segmento = substr($barcode, 7, 5);
        $segmentoValor = (float) $segmento;

        if ($modelo === 3) {
            $total = round($segmentoValor / 100, 2);
            $quantidade = $total > 0 ? round($total / $preco, 3) : 1;

            return [
                'quantidade' => max(0.001, $quantidade),
                'preco' => $preco,
                'total' => $total,
            ];
        }

        $quantidade = $segmentoValor;
        $unidade = strtoupper((string) ($product->unidade ?? 'UN'));

        if (! in_array($unidade, ['UN', 'PC'], true)) {
            $quantidade = round($quantidade / 1000, 3);
        } else {
            $quantidade = floor($quantidade);
        }

        $quantidade = max(0.001, $quantidade);
        $total = round($quantidade * $preco, 2);

        return [
            'quantidade' => $quantidade,
            'preco' => $preco,
            'total' => $total,
        ];
    }
}
