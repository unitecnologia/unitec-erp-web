<?php

namespace Tests\Unit;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ProductDeletionGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductDeletionGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_permite_excluir_produto_sem_vinculos(): void
    {
        $product = Product::query()->create([
            'codigo' => 'DEL1',
            'descricao' => 'PRODUTO SEM VINCULO',
            'preco_venda' => 1,
            'ativo' => true,
        ]);

        $guard = new ProductDeletionGuard();

        $this->assertTrue($guard->canDelete($product));
        $this->assertSame([], $guard->blockingReasons($product));
    }

    public function test_bloqueia_produto_com_venda(): void
    {
        $product = Product::query()->create([
            'codigo' => 'DEL2',
            'descricao' => 'PRODUTO COM VENDA',
            'preco_venda' => 2,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'numero' => '000001',
            'data' => now()->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => null,
            'vendedor_id' => null,
            'total' => 2,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        CompraItem::query()->create([
            'compra_id' => Compra::query()->create([
                'numero' => '000001',
                'data_emissao' => now()->toDateString(),
                'data_entrada' => now()->toDateString(),
                'fornecedor_id' => null,
                'total' => 0,
                'status' => Compra::STATUS_FECHADA,
            ])->id,
            'product_id' => $product->id,
            'quantidade' => 1,
            'valor_unitario' => 1,
            'total' => 1,
        ]);

        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $product->id,
            'quantidade' => 1,
            'valor_item' => 2,
            'total' => 2,
        ]);

        $guard = new ProductDeletionGuard();
        $reasons = $guard->blockingReasons($product);

        $this->assertFalse($guard->canDelete($product));
        $this->assertContains('Compras', $reasons);
        $this->assertContains('Vendas', $reasons);
        $this->assertStringContainsString('Compras', $guard->message($reasons));
        $this->assertStringContainsString('Vendas', $guard->message($reasons));
    }
}
