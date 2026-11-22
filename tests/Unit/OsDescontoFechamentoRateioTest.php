<?php

namespace Tests\Unit;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Os\OsDescontoFechamentoRateio;
use Tests\TestCase;

class OsDescontoFechamentoRateioTest extends TestCase
{
    public function test_rateio_proporcional_respeita_desconto_individual_e_centavos_no_ultimo(): void
    {
        $itens = [
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '100,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '100,00',
            ],
            [
                'tipo' => 'S',
                'qtd' => '1,000',
                'preco' => '50,00',
                'acrescimo' => '0,00',
                'desconto' => '10,00',
                'total' => '40,00',
            ],
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '10,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '10,00',
            ],
        ];

        $resultado = OsDescontoFechamentoRateio::aplicar($itens, 10.00);

        $this->assertSame(10.00, $resultado['desconto_aplicado']);

        $d0 = ErpMoney::parseBr($resultado['itens'][0]['desconto']);
        $d1 = ErpMoney::parseBr($resultado['itens'][1]['desconto']);
        $d2 = ErpMoney::parseBr($resultado['itens'][2]['desconto']);

        // Base 150: 100→6,67; 40→2,67; último 10−6,67−2,67 = 0,66 (+ desconto individual 10 no 2º).
        $this->assertEqualsWithDelta(6.67, $d0, 0.001);
        $this->assertEqualsWithDelta(12.67, $d1, 0.001);
        $this->assertEqualsWithDelta(0.66, $d2, 0.001);

        $t0 = ErpMoney::parseBr($resultado['itens'][0]['total']);
        $t1 = ErpMoney::parseBr($resultado['itens'][1]['total']);
        $t2 = ErpMoney::parseBr($resultado['itens'][2]['total']);

        $this->assertEqualsWithDelta(93.33, $t0, 0.001);
        $this->assertEqualsWithDelta(37.33, $t1, 0.001);
        $this->assertEqualsWithDelta(9.34, $t2, 0.001);
        $this->assertEqualsWithDelta(140.00, $t0 + $t1 + $t2, 0.001);
    }

    public function test_ignora_linhas_com_total_zero_e_nao_duplica_sem_desconto(): void
    {
        $itens = [
            [
                'qtd' => '1,000',
                'preco' => '20,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '20,00',
            ],
            [
                'qtd' => '1,000',
                'preco' => '0,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '0,00',
            ],
        ];

        $sem = OsDescontoFechamentoRateio::aplicar($itens, 0);
        $this->assertSame(0.0, $sem['desconto_aplicado']);
        $this->assertSame('0,00', $sem['itens'][0]['desconto']);

        $com = OsDescontoFechamentoRateio::aplicar($itens, 5);
        $this->assertSame(5.0, $com['desconto_aplicado']);
        $this->assertEqualsWithDelta(5.0, ErpMoney::parseBr($com['itens'][0]['desconto']), 0.001);
        $this->assertEqualsWithDelta(0.0, ErpMoney::parseBr($com['itens'][1]['desconto']), 0.001);
        $this->assertEqualsWithDelta(15.0, ErpMoney::parseBr($com['itens'][0]['total']), 0.001);
    }
}
