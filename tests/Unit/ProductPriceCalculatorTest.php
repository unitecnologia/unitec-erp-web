<?php

namespace Tests\Unit;

use App\Support\Erp\BrDecimal;
use App\Support\Erp\ProductPriceCalculator;
use PHPUnit\Framework\TestCase;

class ProductPriceCalculatorTest extends TestCase
{
    public function test_recalculate_from_compra_parses_brazilian_decimal_strings(): void
    {
        $result = ProductPriceCalculator::recalculateFromCompra([
            'preco_compra' => '10,50',
            'pct_custos' => '15,00',
            'pct_lucro' => '20,00',
        ]);

        $this->assertSame(12.08, $result['preco_custo']);
        $this->assertSame(14.5, $result['preco_venda']);
    }

    public function test_recalculate_from_venda_parses_brazilian_decimal_strings(): void
    {
        $result = ProductPriceCalculator::recalculateFromVenda([
            'preco_custo' => '100,00',
            'preco_venda' => '125,50',
        ]);

        $this->assertSame(25.5, $result['pct_lucro']);
    }

    public function test_recalculate_before_save_preserves_manual_preco_venda(): void
    {
        $result = ProductPriceCalculator::recalculateBeforeSave([
            'preco_compra' => '10,00',
            'pct_custos' => '0,00',
            'pct_lucro' => '0,00',
            'preco_custo' => '10,00',
            'preco_venda' => '25,00',
        ]);

        $this->assertSame(10.0, $result['preco_custo']);
        $this->assertSame(25.0, BrDecimal::parse($result['preco_venda'], 2));
        $this->assertSame(150.0, $result['pct_lucro']);
    }

    public function test_recalculate_before_save_derives_venda_when_missing(): void
    {
        $result = ProductPriceCalculator::recalculateBeforeSave([
            'preco_compra' => '10,00',
            'pct_custos' => '0,00',
            'pct_lucro' => '50,00',
            'preco_custo' => '0,00',
            'preco_venda' => '0,00',
        ]);

        $this->assertSame(10.0, $result['preco_custo']);
        $this->assertSame(15.0, $result['preco_venda']);
        $this->assertSame(50.0, BrDecimal::parse($result['pct_lucro'], 2));
    }

    public function test_recalculate_before_save_updates_custo_from_compra_without_touching_manual_venda(): void
    {
        $result = ProductPriceCalculator::recalculateBeforeSave([
            'preco_compra' => '100,00',
            'pct_custos' => '10,00',
            'pct_lucro' => '999,00',
            'preco_custo' => '1,00',
            'preco_venda' => '200,00',
        ]);

        $this->assertSame(110.0, $result['preco_custo']);
        $this->assertSame(200.0, BrDecimal::parse($result['preco_venda'], 2));
        $this->assertSame(81.82, $result['pct_lucro']);
    }
}
