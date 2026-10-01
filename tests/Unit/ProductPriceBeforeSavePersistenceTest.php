<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductPriceCalculator;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ProductPriceBeforeSavePersistenceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_venda_manual_persiste_em_products_e_empresa_precos_igual_ao_pdv(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA PRECO SAVE',
            'ativo' => true,
        ]);

        $formData = ProductPriceCalculator::recalculateBeforeSave([
            'preco_compra' => '10,00',
            'pct_custos' => '0,00',
            'pct_lucro' => '0,00',
            'preco_custo' => '10,00',
            'preco_venda' => '25,90',
            'preco_atacado' => '0,00',
            'preco_especial' => '0,00',
        ]);

        $product = Product::query()->create([
            'codigo' => 'T-PRECO-1',
            'descricao' => 'TESTE PRECO MANUAL SAVE',
            'unidade' => 'UN',
            'ativo' => true,
            'preco_compra' => $formData['preco_compra'] ?? 10,
            'pct_custos' => $formData['pct_custos'] ?? 0,
            'preco_custo' => $formData['preco_custo'],
            'pct_lucro' => $formData['pct_lucro'],
            'preco_venda' => $formData['preco_venda'],
            'preco_atacado' => 0,
            'preco_especial' => 0,
        ]);

        $service = app(ProductEmpresaPrecoService::class);
        $prices = $service->extractFromFormData($formData);
        $service->upsert($product, (int) $empresa->id, $prices);

        $product->refresh();
        $overlay = ProductEmpresaPreco::query()
            ->where('product_id', $product->id)
            ->where('empresa_id', $empresa->id)
            ->first();

        $this->assertNotNull($overlay);
        $this->assertEquals(25.9, (float) $product->preco_venda);
        $this->assertEquals(10.0, (float) $product->preco_custo);
        $this->assertEquals(25.9, (float) $overlay->preco_venda);
        $this->assertEquals(10.0, (float) $overlay->preco_compra);
        $this->assertSame(25.9, $service->resolvePrecoVenda($product->fresh(), (int) $empresa->id));
    }

    public function test_antes_da_correcao_recalculate_from_compra_zeraria_margem_e_baixaria_venda(): void
    {
        // Documenta o bug antigo: compra + margem 0 sobrescrevia venda manual.
        $bug = ProductPriceCalculator::recalculateFromCompra([
            'preco_compra' => '10,00',
            'pct_custos' => '0,00',
            'pct_lucro' => '0,00',
            'preco_venda' => '25,90',
        ]);
        $this->assertSame(10.0, $bug['preco_venda']);

        $fixed = ProductPriceCalculator::recalculateBeforeSave([
            'preco_compra' => '10,00',
            'pct_custos' => '0,00',
            'pct_lucro' => '0,00',
            'preco_venda' => '25,90',
        ]);
        $this->assertSame(25.9, round((float) (is_numeric($fixed['preco_venda'])
            ? $fixed['preco_venda']
            : \App\Support\Erp\BrDecimal::parse($fixed['preco_venda'], 2)), 2));
    }
}
