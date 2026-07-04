<?php

namespace Tests\Unit;

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
}
