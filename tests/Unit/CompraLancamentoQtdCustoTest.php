<?php

namespace Tests\Unit;

use App\Support\Erp\BrDecimal;
use PHPUnit\Framework\TestCase;

/**
 * Garante a regra da grade: V. Custo = Preço (valor cheio) ÷ Qtd.Compra.
 */
class CompraLancamentoQtdCustoTest extends TestCase
{
    public function test_dilui_custo_ao_aumentar_quantidade(): void
    {
        $precoCheio = BrDecimal::parse('377,16', 4);
        $qtd = BrDecimal::parse('8', 3);

        $this->assertSame(377.16, $precoCheio);
        $this->assertSame(8.0, $qtd);

        $vlCusto = $qtd > 0 ? round($precoCheio / $qtd, 4) : 0.0;

        $this->assertSame(47.145, $vlCusto);
        $this->assertSame('8,000', number_format($qtd, 3, ',', '.'));
        $this->assertSame('47,15', number_format($vlCusto, 2, ',', '.'));
    }

    public function test_parse_quantidade_com_mascara_brasileira(): void
    {
        $this->assertSame(8.0, BrDecimal::parse('8,000', 3));
        $this->assertSame(1.5, BrDecimal::parse('1,500', 3));
    }
}
