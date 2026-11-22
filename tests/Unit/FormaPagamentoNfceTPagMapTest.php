<?php

namespace Tests\Unit;

use App\Support\Fiscal\FormaPagamentoNfceTPagMap;
use Tests\TestCase;

class FormaPagamentoNfceTPagMapTest extends TestCase
{
    public function test_pix_nfce_usa_tpag_20_nao_17(): void
    {
        $this->assertSame('20', FormaPagamentoNfceTPagMap::fromTipo('pix'));
        $this->assertSame('20', FormaPagamentoNfceTPagMap::fromDescricao('PIX'));
        $this->assertSame('20', FormaPagamentoNfceTPagMap::fromDescricao('PIX LOJA'));
    }

    public function test_tipos_cartao_dinheiro_crediario(): void
    {
        $this->assertSame('01', FormaPagamentoNfceTPagMap::fromTipo('dinheiro'));
        $this->assertSame('03', FormaPagamentoNfceTPagMap::fromTipo('cartao_credito'));
        $this->assertSame('04', FormaPagamentoNfceTPagMap::fromTipo('cartao_debito'));
        $this->assertSame('04', FormaPagamentoNfceTPagMap::fromTipo('tef'));
        $this->assertSame('05', FormaPagamentoNfceTPagMap::fromTipo('crediario'));
        $this->assertSame('15', FormaPagamentoNfceTPagMap::fromTipo('boleto'));
    }

    public function test_descricao_supertef_credito_debito(): void
    {
        $this->assertSame('03', FormaPagamentoNfceTPagMap::fromDescricao('SUPERTEF CREDITO'));
        $this->assertSame('04', FormaPagamentoNfceTPagMap::fromDescricao('SUPERTEF DEBITO'));
        $this->assertSame('01', FormaPagamentoNfceTPagMap::fromDescricao('DINHEIRO'));
    }

    public function test_helpers_cartao_eletronico(): void
    {
        $this->assertTrue(FormaPagamentoNfceTPagMap::isCartao('03'));
        $this->assertTrue(FormaPagamentoNfceTPagMap::isCartao('04'));
        $this->assertFalse(FormaPagamentoNfceTPagMap::isCartao('20'));
        $this->assertTrue(FormaPagamentoNfceTPagMap::isEletronico('20'));
        $this->assertTrue(FormaPagamentoNfceTPagMap::isEletronico('03'));
        $this->assertFalse(FormaPagamentoNfceTPagMap::isEletronico('01'));
    }
}
