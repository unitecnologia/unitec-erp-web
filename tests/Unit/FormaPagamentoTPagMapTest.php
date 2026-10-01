<?php

namespace Tests\Unit;

use App\Models\FormaPagamento;
use App\Support\Fiscal\FormaPagamentoTPagMap;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FormaPagamentoTPagMapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tipo_define_tpag_sem_usar_descricao(): void
    {
        $this->assertSame('17', FormaPagamentoTPagMap::fromTipo('pix'));
        $this->assertSame('15', FormaPagamentoTPagMap::fromTipo('boleto'));
        $this->assertSame('03', FormaPagamentoTPagMap::fromTipo('cartao_credito'));
        $this->assertSame('04', FormaPagamentoTPagMap::fromTipo('cartao_debito'));
        $this->assertSame('01', FormaPagamentoTPagMap::fromTipo('dinheiro'));
        $this->assertSame('05', FormaPagamentoTPagMap::fromTipo('crediario'));
    }

    public function test_tef_nao_e_meio_valido_na_nfe(): void
    {
        $this->assertFalse(FormaPagamentoTPagMap::isTipoPermitidoNaNfe('tef'));
        $this->assertSame('01', FormaPagamentoTPagMap::fromTipo('tef'));
        $this->assertTrue(FormaPagamentoTPagMap::isTipoPermitidoNaNfe('cartao_debito'));
    }

    public function test_descricao_nao_influencia_quando_tipo_e_pix(): void
    {
        $forma = FormaPagamento::query()->create([
            'codigo' => 99100 + random_int(0, 899),
            'descricao' => 'PIX SANTANDER',
            'tipo' => 'pix',
            'tipo_movimento' => 'caixa',
            'ativo' => true,
            'aparece_venda' => true,
        ]);

        $this->assertSame('17', FormaPagamentoTPagMap::fromMeioPgto((string) $forma->id));
    }

    public function test_legado_meio_pgto_ainda_funciona(): void
    {
        $this->assertSame('03', FormaPagamentoTPagMap::fromMeioPgto('cartao'));
        $this->assertSame('15', FormaPagamentoTPagMap::fromMeioPgto('boleto'));
        $this->assertSame('17', FormaPagamentoTPagMap::fromMeioPgto('pix'));
        $this->assertSame('01', FormaPagamentoTPagMap::fromMeioPgto('dinheiro'));
    }
}
