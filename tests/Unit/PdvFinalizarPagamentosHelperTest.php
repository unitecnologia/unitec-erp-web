<?php

namespace Tests\Unit;

use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use PHPUnit\Framework\TestCase;

class PdvFinalizarPagamentosHelperTest extends TestCase
{
    public function test_aplica_crediario_exclusivo_zerando_dinheiro_padrao(): void
    {
        $pagamentos = [
            ['forma' => 'DINHEIRO', 'atalho' => 'A', 'valor' => '2.621,00'],
            ['forma' => 'PIX', 'atalho' => 'P', 'valor' => '0,00'],
            ['forma' => 'POS DEBITO', 'atalho' => 'D', 'valor' => '0,00'],
            ['forma' => 'POS CREDITO', 'atalho' => 'C', 'valor' => '0,00'],
            ['forma' => 'CREDIÁRIO', 'atalho' => 'R', 'valor' => '0,00'],
            ['forma' => 'CHEQUE', 'atalho' => 'H', 'valor' => '0,00'],
        ];

        $resultado = PdvFinalizarPagamentosHelper::aplicarFormaPrazoExclusiva($pagamentos, 4, 2621.00);

        $this->assertSame('0,00', $resultado[0]['valor']);
        $this->assertSame('2.621,00', $resultado[4]['valor']);
    }

    public function test_pos_credito_nao_e_forma_a_prazo(): void
    {
        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaAPrazo('POS CREDITO'));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaAPrazo('CREDIÁRIO'));
    }
}
