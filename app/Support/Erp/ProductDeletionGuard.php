<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\CompraItem;
use App\Models\NfeItem;
use App\Models\OrcamentoItem;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\Fabricacao;
use App\Models\FabricacaoItem;
use App\Models\ProductReceita;
use App\Models\ProductReceitaItem;
use App\Models\VendaItem;

final class ProductDeletionGuard
{
    /**
     * @return list<string>
     */
    public function blockingReasons(Product | int $product): array
    {
        $productId = $product instanceof Product ? (int) $product->getKey() : $product;
        $reasons = [];

        foreach ($this->checks($productId) as $label => $hasLink) {
            if ($hasLink) {
                $reasons[] = $label;
            }
        }

        return $reasons;
    }

    public function canDelete(Product | int $product): bool
    {
        return $this->blockingReasons($product) === [];
    }

    /**
     * @param  list<string>  $reasons
     */
    public function message(array $reasons): string
    {
        if ($reasons === []) {
            return '';
        }

        return 'O produto não pode ser excluído pois possui vínculos com: '
            . implode(', ', $reasons)
            . '.';
    }

    /**
     * @return array<string, bool>
     */
    private function checks(int $productId): array
    {
        return [
            'Compras' => CompraItem::query()->where('product_id', $productId)->exists(),
            'Vendas' => VendaItem::query()->where('product_id', $productId)->exists(),
            'Vendas PDV' => PdvVendaItem::query()->where('product_id', $productId)->exists(),
            'NF-e / NFC-e' => NfeItem::query()->where('product_id', $productId)->exists(),
            'Orçamentos' => OrcamentoItem::query()->where('product_id', $productId)->exists(),
            'Ajustes de estoque' => AjusteEstoque::query()->where('product_id', $productId)->exists(),
            'Composição de outros produtos' => ProductComposition::query()
                ->where('component_product_id', $productId)
                ->exists(),
            'Receita de fabricação' => ErpSchema::hasTable('product_receitas')
                && ProductReceita::query()->where('product_id', $productId)->exists(),
            'Ingrediente de receita' => ErpSchema::hasTable('product_receita_itens')
                && ProductReceitaItem::query()->where('component_product_id', $productId)->exists(),
            'Fabricação (acabado)' => ErpSchema::hasTable('fabricacoes')
                && Fabricacao::query()->where('product_id', $productId)->exists(),
            'Fabricação (ingrediente)' => ErpSchema::hasTable('fabricacao_itens')
                && FabricacaoItem::query()->where('component_product_id', $productId)->exists(),
        ];
    }
}
