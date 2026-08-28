<?php

namespace Tests\Unit;

use App\Models\Unidade;
use PHPUnit\Framework\TestCase;

class UnidadeOptionLabelTest extends TestCase
{
    public function test_option_label_caixa_alta(): void
    {
        $this->assertSame('KG — QUILOGRAMA', Unidade::optionLabel('KG', 'QUILOGRAMA'));
        $this->assertSame('M3 — METRO CÚBICO', Unidade::optionLabel('M3', 'METRO CUBICO'));
        $this->assertSame('CX — CAIXA', Unidade::optionLabel('cx', 'CAIXA'));
        $this->assertSame('PC — PEÇA', Unidade::optionLabel('PC', 'PC'));
    }

    public function test_custom_unit_uppercase(): void
    {
        $this->assertSame('XYZ — MINHA UNIDADE', Unidade::optionLabel('XYZ', 'MINHA UNIDADE'));
    }
}
