<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Empresa;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPrecificacaoEmpresaSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_troca_empresa_na_precificacao_nao_altera_sessao_do_erp(): void
    {
        $empresaA = Empresa::query()->create([
            'nome' => 'EMPRESA A PRECIF',
            'ativo' => true,
        ]);
        $empresaB = Empresa::query()->create([
            'nome' => 'EMPRESA B PRECIF',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresaA->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresaA->id]);
        $this->actingAs($user);

        $product = Product::query()->create([
            'codigo' => 'PCF001',
            'descricao' => 'PRODUTO PRECIF SESSAO',
            'preco_venda' => 4.75,
            'preco_custo' => 4.00,
            'preco_compra' => 4.00,
            'estoque' => 1,
            'ativo' => true,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertSet('productFormEmpresaId', (int) $empresaA->id)
            ->call('openProductPrecificacao')
            ->assertSet('productPrecificacaoOpen', true)
            ->call('switchPrecificacaoEmpresa', $empresaB->id)
            ->assertSet('productFormEmpresaId', (int) $empresaB->id)
            ->assertSet('productPrecificacaoOpen', true);

        $this->assertSame((int) $empresaA->id, (int) session('erp_empresa_id'));
    }

    public function test_switch_product_form_empresa_nao_grava_sessao(): void
    {
        $empresaA = Empresa::query()->create([
            'nome' => 'EMPRESA A FORM',
            'ativo' => true,
        ]);
        $empresaB = Empresa::query()->create([
            'nome' => 'EMPRESA B FORM',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresaA->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresaA->id]);
        $this->actingAs($user);

        $product = Product::query()->create([
            'codigo' => 'PCF002',
            'descricao' => 'PRODUTO FORM SESSAO',
            'preco_venda' => 10,
            'preco_custo' => 5,
            'estoque' => 1,
            'ativo' => true,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->call('switchProductFormEmpresa', $empresaB->id)
            ->assertSet('productFormEmpresaId', (int) $empresaB->id);

        $this->assertSame((int) $empresaA->id, (int) session('erp_empresa_id'));
    }
}
