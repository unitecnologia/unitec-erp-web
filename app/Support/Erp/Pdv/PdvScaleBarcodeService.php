<?php

namespace App\Support\Erp\Pdv;

use App\Models\Product;
use App\Support\Erp\Balanca\BalancaEtiquetaLayout;

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
        $digitos = $this->config->digitosBalanca();
        $prefixoBarra = $this->config->prefixoCodBarraBalanca();
        $filler = BalancaEtiquetaLayout::fillerLength($modelo);
        $valorLen = BalancaEtiquetaLayout::valorLength($modelo);

        // Preferência: layout configurado (prefixo EAN + dígitos + filler).
        $valorStart = strlen($prefixoBarra) + $digitos + $filler;

        // Fallback: se o prefixo do produto for maior (ex.: já inclui o "2"), usa o comprimento dele.
        if (strlen($prefixoProduto) > $valorStart) {
            $valorStart = strlen($prefixoProduto);
        }

        // Compat Delphi/legado: muitos cadastros usam 7 chars (2 + 6 dígitos).
        if ($valorStart < 7 && strlen($barcode) >= 12 && $digitos === 6 && $filler === 0) {
            $valorStart = 7;
        }

        $segmento = substr($barcode, $valorStart, $valorLen);

        if ($segmento === '' || ! ctype_digit($segmento)) {
            // Último fallback do comportamento anterior.
            $segmento = substr($barcode, 7, 5);
        }

        $segmentoValor = (float) $segmento;

        if (BalancaEtiquetaLayout::isTotalPrice($modelo)) {
            $divisor = $valorLen >= 6 ? 100 : 100;
            $total = round($segmentoValor / $divisor, 2);
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
