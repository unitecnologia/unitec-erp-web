<?php

namespace Tests\Unit;

use App\Support\Erp\Financeiro\ContaPagarBaixaJurosPadrao;
use PHPUnit\Framework\TestCase;

class ContaPagarBaixaJurosPadraoTest extends TestCase
{
    public function test_um_porcento_ao_mes_pro_rata_4_dias(): void
    {
        $saldo = 1603.58;
        $dias = ContaPagarBaixaJurosPadrao::diasAtraso('2026-11-12', '2026-11-16');
        $juros = ContaPagarBaixaJurosPadrao::juros($saldo, $dias, 1);
        $multa = ContaPagarBaixaJurosPadrao::multa($saldo, $dias, 2);
        $total = round($saldo + $juros + $multa, 2);

        $this->assertSame(4, $dias);
        $this->assertSame(2.14, $juros);
        $this->assertSame(32.07, $multa);
        $this->assertSame(1637.79, $total);
    }

    public function test_getcard_15_dias_1_porcento_ao_mes(): void
    {
        $saldo = 198.00;
        $dias = ContaPagarBaixaJurosPadrao::diasAtraso('2026-11-01', '2026-11-16');
        $juros = ContaPagarBaixaJurosPadrao::juros($saldo, $dias, 1);
        $multa = ContaPagarBaixaJurosPadrao::multa($saldo, $dias, 2);

        $this->assertSame(15, $dias);
        $this->assertSame(0.99, $juros);
        $this->assertSame(3.96, $multa);
        $this->assertSame(202.95, round($saldo + $juros + $multa, 2));
    }

    public function test_sem_atraso_nao_cobra_juros_nem_multa(): void
    {
        $this->assertSame(0, ContaPagarBaixaJurosPadrao::diasAtraso('2026-11-16', '2026-11-16'));
        $this->assertSame(0.0, ContaPagarBaixaJurosPadrao::juros(1603.58, 0, 1));
        $this->assertSame(0.0, ContaPagarBaixaJurosPadrao::multa(1603.58, 0, 2));
    }

    public function test_data_vazia_nao_conta_atraso(): void
    {
        $this->assertFalse(ContaPagarBaixaJurosPadrao::dataIsoValida(''));
        $this->assertFalse(ContaPagarBaixaJurosPadrao::dataIsoValida('dd/mm/aaaa'));
        $this->assertTrue(ContaPagarBaixaJurosPadrao::dataIsoValida('2026-11-16'));
        $this->assertSame(0, ContaPagarBaixaJurosPadrao::diasAtraso('2026-11-12', ''));
    }
}
