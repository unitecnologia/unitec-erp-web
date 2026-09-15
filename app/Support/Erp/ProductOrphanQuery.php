<?php

namespace App\Support\Erp;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ProductOrphanQuery
{
    /**
     * Produtos sem vínculo comercial/estoque (candidatos a exclusão).
     *
     * @return Builder<Product>
     */
    public function builder(): Builder
    {
        $q = Product::query()->from('products');

        $q->where(function (Builder $stock): void {
            $stock->whereNull('products.estoque')
                ->orWhere('products.estoque', 0);
        });

        if (Schema::hasTable('product_estoque_saldos')) {
            $q->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('product_estoque_saldos')
                    ->whereColumn('product_estoque_saldos.product_id', 'products.id')
                    ->where('product_estoque_saldos.quantidade', '!=', 0);
            });
        }

        foreach ($this->linkTables() as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $q->whereNotExists(function ($sub) use ($table, $column): void {
                $sub->selectRaw('1')
                    ->from($table)
                    ->whereColumn("{$table}.{$column}", 'products.id');
            });
        }

        return $q;
    }

    /**
     * @return array<string, string> table => column referencing products.id
     */
    private function linkTables(): array
    {
        return [
            'compra_itens' => 'product_id',
            'venda_itens' => 'product_id',
            'pdv_venda_itens' => 'product_id',
            'nfe_itens' => 'product_id',
            'nfse_itens' => 'product_id',
            'orcamento_itens' => 'product_id',
            'ordem_servico_itens' => 'product_id',
            'ordens_servico' => 'produto_id',
            'devolucao_venda_itens' => 'product_id',
            'devolucao_compra_itens' => 'product_id',
            'nota_fornecedor_itens' => 'product_id',
            'entrega_itens' => 'product_id',
            'outras_saida_movimento_itens' => 'product_id',
            'promocao_itens' => 'product_id',
            'ajustes_estoque' => 'product_id',
            'estoque_reservas' => 'product_id',
            'estoque_movimentacoes' => 'produto_id',
            'product_compositions' => 'component_product_id',
        ];
    }
}
