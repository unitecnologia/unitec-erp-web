<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\ProductListagemReport;
use PHPUnit\Framework\TestCase;

class ProductListagemReportTest extends TestCase
{
    public function test_resolve_columns_usa_padrao_quando_nao_informado(): void
    {
        $columns = ProductListagemReport::resolveColumns(null);

        $this->assertSame(ProductListagemReport::defaultColumns(), $columns);
        $this->assertContains('preco_compra', $columns);
        $this->assertNotContains('total', $columns);
        $this->assertNotContains('validade', $columns);
    }

    public function test_exibe_validade_do_produto(): void
    {
        $product = new \App\Models\Product;
        $product->setRawAttributes(['validade' => '2026-12-31']);

        $this->assertSame('31/12/2026', ProductListagemReport::cellValue($product, 'validade'));
    }

    public function test_validade_vencida_quando_data_passou(): void
    {
        $product = new \App\Models\Product;
        $product->setRawAttributes(['validade' => now()->subDay()->toDateString()]);

        $this->assertTrue($product->validadeVencida());
        $this->assertTrue(ProductListagemReport::validadeVencida($product));
    }

    public function test_validade_nao_vencida_quando_data_futura(): void
    {
        $product = new \App\Models\Product;
        $product->setRawAttributes(['validade' => now()->addDay()->toDateString()]);

        $this->assertFalse($product->validadeVencida());
    }

    public function test_exibe_custo_de_compra_do_produto(): void
    {
        $product = new \App\Models\Product([
            'preco_compra' => 12.5,
        ]);

        $this->assertSame('12,50', ProductListagemReport::cellValue($product, 'preco_compra'));
    }

    public function test_totaliza_colunas_do_relatorio(): void
    {
        $products = [
            new \App\Models\Product([
                'preco_compra' => 10,
                'preco_venda' => 20,
                'estoque' => 2,
            ]),
            new \App\Models\Product([
                'preco_compra' => 5.5,
                'preco_venda' => 15,
                'estoque' => 3,
            ]),
        ];

        $columns = ['descricao', 'preco_venda', 'preco_compra', 'estoque'];
        $totals = ProductListagemReport::columnTotals($products, $columns);

        $this->assertSame('TOTAL', $totals['descricao']);
        $this->assertSame('35,00', $totals['preco_venda']);
        $this->assertSame('15,50', $totals['preco_compra']);
        $this->assertSame('5', $totals['estoque']);
    }
}
