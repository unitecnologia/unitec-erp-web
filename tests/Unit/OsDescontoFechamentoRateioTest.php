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

    public function test_desconto_100_por_cento_zera_totais_e_respeita_centavos(): void
    {
        $itens = [
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '33,33',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '33,33',
            ],
            [
                'tipo' => 'S',
                'qtd' => '1,000',
                'preco' => '33,33',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '33,33',
            ],
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '33,34',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '33,34',
            ],
        ];

        $resultado = OsDescontoFechamentoRateio::aplicar($itens, 100.00);

        $this->assertSame(100.00, $resultado['desconto_aplicado']);

        $somaTotais = 0.0;
        foreach ($resultado['itens'] as $item) {
            $somaTotais += ErpMoney::parseBr($item['total']);
            $this->assertEqualsWithDelta(
                ErpMoney::parseBr($item['preco']),
                ErpMoney::parseBr($item['desconto']),
                0.001,
            );
        }

        $this->assertEqualsWithDelta(0.0, $somaTotais, 0.001);
    }

    public function test_desconto_maior_que_base_e_limitado_ao_total(): void
    {
        $itens = [
            [
                'qtd' => '1,000',
                'preco' => '50,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '50,00',
            ],
        ];

        $resultado = OsDescontoFechamentoRateio::aplicar($itens, 999.99);

        $this->assertSame(50.0, $resultado['desconto_aplicado']);
        $this->assertEqualsWithDelta(0.0, ErpMoney::parseBr($resultado['itens'][0]['total']), 0.001);
    }

    public function test_rateio_preserva_acrescimo_do_item_no_recalculo(): void
    {
        $itens = [
            [
                'qtd' => '1,000',
                'preco' => '100,00',
                'acrescimo' => '10,00',
                'desconto' => '0,00',
                'total' => '110,00',
            ],
            [
                'qtd' => '1,000',
                'preco' => '90,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '90,00',
            ],
        ];

        $resultado = OsDescontoFechamentoRateio::aplicar($itens, 20.00);

        $this->assertSame(20.0, $resultado['desconto_aplicado']);
        $this->assertEqualsWithDelta(10.0, ErpMoney::parseBr($resultado['itens'][0]['acrescimo']), 0.001);

        $t0 = ErpMoney::parseBr($resultado['itens'][0]['total']);
        $t1 = ErpMoney::parseBr($resultado['itens'][1]['total']);

        // Base 200: 110→11,00; último 20−11 = 9,00 → totais 99 e 81.
        $this->assertEqualsWithDelta(99.0, $t0, 0.001);
        $this->assertEqualsWithDelta(81.0, $t1, 0.001);
        $this->assertEqualsWithDelta(180.0, $t0 + $t1, 0.001);
    }

    public function test_desconto_por_item_ja_aplicado_entra_na_base_do_f4(): void
    {
        $itens = [
            [
                'qtd' => '1,000',
                'preco' => '100,00',
                'acrescimo' => '0,00',
                'desconto' => '20,00',
                'total' => '80,00',
            ],
            [
                'qtd' => '1,000',
                'preco' => '20,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '20,00',
            ],
        ];

        $resultado = OsDescontoFechamentoRateio::aplicar($itens, 10.00);

        $this->assertSame(10.0, $resultado['desconto_aplicado']);
        // Base 100: 80→8,00; último 2,00 (+20 individual no 1º → desconto 28).
        $this->assertEqualsWithDelta(28.0, ErpMoney::parseBr($resultado['itens'][0]['desconto']), 0.001);
        $this->assertEqualsWithDelta(2.0, ErpMoney::parseBr($resultado['itens'][1]['desconto']), 0.001);
        $this->assertEqualsWithDelta(90.0, ErpMoney::parseBr($resultado['itens'][0]['total']) + ErpMoney::parseBr($resultado['itens'][1]['total']), 0.001);
    }
}
