<?php

namespace App\Support\Erp\Pdv;

use App\Models\PriceTable;
use App\Models\Product;
use App\Models\ProductPriceTableItem;
use Carbon\Carbon;

final class PdvProductPriceService
{
    public function __construct(
        private readonly PdvConfig $config,
    ) {}

    public function resolvePrecoVenda(Product $product, float $quantidade = 1): float
    {
        $preco = $this->resolvePrecoBase($product, $quantidade);

        if ($this->config->habilitarTabelaPreco() && ($product->usa_tab_preco ?? false)) {
            $precoTabela = $this->resolvePrecoTabela($product, $quantidade);

            if ($precoTabela !== null) {
                return $precoTabela;
            }
        }

        return $preco;
    }

    public function resolvePrecoTabela(Product $product, float $quantidade = 1, ?int $priceTableId = null): ?float
    {
        if (! $this->config->habilitarTabelaPreco() || ! ($product->usa_tab_preco ?? false)) {
            return null;
        }

        $tableId = $priceTableId ?? $this->config->priceTableId();

        if (! $tableId) {
            return null;
        }

        $item = ProductPriceTableItem::query()
            ->where('product_id', $product->id)
            ->where('price_table_id', $tableId)
            ->first();

        if (! $item) {
            return null;
        }

        $valor = (float) $item->valor;

        if ($valor > 0) {
            return round($valor, 2);
        }

        $fator = (float) $item->fator;

        if ($fator > 0) {
            $base = $this->resolvePrecoBase($product, $quantidade);

            return round($base * $fator, 2);
        }

        return null;
    }

    public function emPromocao(Product $product, ?Carbon $data = null): bool
    {
        $data ??= Carbon::today();

        if (blank($product->promo_data_inicio) || blank($product->promo_data_fim)) {
            return false;
        }

        return $data->between(
            Carbon::parse($product->promo_data_inicio)->startOfDay(),
            Carbon::parse($product->promo_data_fim)->endOfDay(),
        );
    }

    public function precoMinimoPermitido(Product $product, float $quantidade = 1): ?float
    {
        $descontoPct = (float) ($product->desconto_pct ?? 0);

        if ($descontoPct <= 0) {
            return null;
        }

        $precoBase = $this->resolvePrecoVenda($product, $quantidade);

        return round($precoBase - ($precoBase * $descontoPct / 100), 2);
    }

    public function validaPrecoInformado(Product $product, float $preco, float $quantidade = 1): ?string
    {
        if ($product->preco_variavel) {
            return null;
        }

        $precoBase = $this->resolvePrecoVenda($product, $quantidade);

        if ($this->emPromocao($product) && ! $this->config->descontoProdPromocao()) {
            if ($preco < $precoBase) {
                return 'Não é permitido descontos para produtos em promoção!';
            }

            return null;
        }

        $minimo = $this->precoMinimoPermitido($product, $quantidade);

        if ($minimo !== null && $preco < $minimo) {
            return 'Desconto maior que o permitido para este produto.';
        }

        return null;
    }

    private function resolvePrecoBase(Product $product, float $quantidade = 1): float
    {
        $quantidade = max(0, $quantidade);
        $hoje = Carbon::today();

        if ($this->emPromocao($product, $hoje)) {
            $preco = (float) ($product->promo_preco_venda ?? $product->preco_venda);

            if ($this->aplicaAtacado($product, $quantidade)) {
                $precoAtacado = (float) ($product->promo_preco_atacado ?? 0);

                if ($precoAtacado > 0) {
                    return $precoAtacado;
                }
            }

            return $preco;
        }

        $preco = (float) $product->preco_venda;

        if ($this->aplicaAtacado($product, $quantidade)) {
            return (float) $product->preco_atacado;
        }

        return $preco;
    }

    private function aplicaAtacado(Product $product, float $quantidade): bool
    {
        $qtdAtacado = (float) ($product->qtd_atacado ?? 0);
        $precoAtacado = (float) ($product->preco_atacado ?? 0);

        return $qtdAtacado > 0 && $precoAtacado > 0 && $quantidade >= $qtdAtacado;
    }
}
