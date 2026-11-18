<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\ContaPagarBaixaValorPagoJuros;
use PHPUnit\Framework\TestCase;

class ContaPagarBaixaValorPagoJurosTest extends TestCase
{
    public function test_pagamento_menor_nao_reduz_juros_padrao(): void
    {
        $calc = ContaPagarBaixaValorPagoJuros::absorverExcesso(
            saldo: 198.00,
            jurosAtual: 29.70,
            multa: 3.96,
            desconto: 0,
            valorPago: 205.02,
        );

        $this->assertFalse($calc['aplicar']);
        $this->assertSame(29.70, $calc['juros']);
        $this->assertSame(231.66, $calc['valor_a_pagar']);
    }

    public function test_valor_igual_mantem_juros_padrao(): void
    {
        $calc = ContaPagarBaixaValorPagoJuros::absorverExcesso(
            saldo: 1603.58,
            jurosAtual: 64.14,
            multa: 32.07,
            desconto: 0,
            valorPago: 1699.79,
        );

        $this->assertFalse($calc['aplicar']);
        $this->assertSame(64.14, $calc['juros']);
    }

    public function test_valor_maior_joga_excesso_nos_juros(): void
    {
        $calc = ContaPagarBaixaValorPagoJuros::absorverExcesso(
            saldo: 1603.58,
            jurosAtual: 64.14,
            multa: 32.07,
            desconto: 0,
            valorPago: 1700.00,
        );

        $this->assertTrue($calc['aplicar']);
        $this->assertSame(64.35, $calc['juros']);
        $this->assertSame(1700.00, $calc['valor_a_pagar']);
    }

    public function test_getcard_pago_acima_do_devido_aumenta_juros(): void
    {
        $calc = ContaPagarBaixaValorPagoJuros::absorverExcesso(
            saldo: 198.00,
            jurosAtual: 29.70,
            multa: 3.96,
            desconto: 0,
            valorPago: 250.00,
        );

        $this->assertTrue($calc['aplicar']);
        $this->assertSame(48.04, $calc['juros']);
    }
}
