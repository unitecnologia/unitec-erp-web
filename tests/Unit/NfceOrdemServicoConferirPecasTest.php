<?php

namespace Tests\Unit;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfce\NfceOrdemServicoService;
use App\Support\Erp\Os\OsDescontoFechamentoRateio;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Conferência F11: totais do modal = mesma conta da NFC-e (sem DB / SEFAZ).
 */
class NfceOrdemServicoConferirPecasTest extends TestCase
{
    public function test_ignora_servicos_e_mostra_desconto_da_peca(): void
    {
        $ordem = $this->ordemComItens([
            $this->peca(1, 'FILTRO OLEO', 1, 100.00, 10.00, 90.00),
            $this->servico(2, 'MAO DE OBRA', 1, 50.00, 0, 50.00),
            $this->peca(3, 'PASTILHA', 2, 20.00, 5.00, 35.00),
        ]);

        $conf = (new NfceOrdemServicoService)->conferirPecas($ordem);

        $this->assertCount(2, $conf['itens']);
        $this->assertSame('FILTRO OLEO', $conf['itens'][0]['descricao']);
        $this->assertEqualsWithDelta(1.0, $conf['itens'][0]['quantidade'], 0.001);
        $this->assertEqualsWithDelta(90.00, $conf['itens'][0]['valor_unitario'], 0.001);
        $this->assertEqualsWithDelta(10.00, $conf['itens'][0]['desconto'], 0.001);
        $this->assertEqualsWithDelta(90.00, $conf['itens'][0]['total'], 0.001);

        $this->assertSame('PASTILHA', $conf['itens'][1]['descricao']);
        $this->assertEqualsWithDelta(2.0, $conf['itens'][1]['quantidade'], 0.001);
        $this->assertEqualsWithDelta(17.50, $conf['itens'][1]['valor_unitario'], 0.001);
        $this->assertEqualsWithDelta(5.00, $conf['itens'][1]['desconto'], 0.001);
        $this->assertEqualsWithDelta(35.00, $conf['itens'][1]['total'], 0.001);

        $this->assertEqualsWithDelta(15.00, $conf['desconto_pecas'], 0.001);
        $this->assertEqualsWithDelta(125.00, $conf['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(125.00, $conf['total'], 0.001);
    }

    public function test_arredondamento_gera_acrescimo_igual_ao_envio_fiscal(): void
    {
        $ordem = $this->ordemComItens([
            $this->peca(10, 'PARAFUSO', 3, 3.3333, 0, 10.00),
            $this->servico(11, 'SERVICO', 1, 80.00, 0, 80.00),
        ]);

        $conf = (new NfceOrdemServicoService)->conferirPecas($ordem);

        $this->assertCount(1, $conf['itens']);
        $this->assertEqualsWithDelta(3.33, $conf['itens'][0]['valor_unitario'], 0.001);
        $this->assertEqualsWithDelta(10.00, $conf['itens'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(9.99, $conf['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto'], 0.001);
        $this->assertEqualsWithDelta(0.01, $conf['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(10.00, $conf['total'], 0.001);
        $this->assertEqualsWithDelta(
            round($conf['subtotal'] - $conf['desconto'] + $conf['acrescimo'], 2),
            $conf['total'],
            0.001,
        );
    }

    public function test_arredondamento_para_cima_gera_desconto_nfce(): void
    {
        // 15,55 / 2 = 7,775 → unitário 7,78 → 15,56; desconto NFC-e 0,01.
        $ordem = $this->ordemComItens([
            $this->peca(20, 'JUNTA', 2, 7.775, 0, 15.55),
        ]);

        $conf = (new NfceOrdemServicoService)->conferirPecas($ordem);

        $this->assertEqualsWithDelta(7.78, $conf['itens'][0]['valor_unitario'], 0.001);
        $this->assertEqualsWithDelta(15.56, $conf['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.01, $conf['desconto'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(15.55, $conf['total'], 0.001);
        $this->assertEqualsWithDelta(
            round($conf['subtotal'] - $conf['desconto'] + $conf['acrescimo'], 2),
            $conf['total'],
            0.001,
        );
    }

    public function test_desconto_geral_rateado_nao_desconta_duas_vezes_e_ignora_servico(): void
    {
        // Bruto: peça 100 + serviço 50 + peça 50 = 200. Desconto geral 20 rateado.
        $rateio = OsDescontoFechamentoRateio::aplicar([
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '100,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '100,00',
                'product_id' => 31,
                'descricao' => 'PECA A',
            ],
            [
                'tipo' => 'S',
                'qtd' => '1,000',
                'preco' => '50,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '50,00',
                'product_id' => null,
                'descricao' => 'SERVICO',
            ],
            [
                'tipo' => 'P',
                'qtd' => '1,000',
                'preco' => '50,00',
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => '50,00',
                'product_id' => 32,
                'descricao' => 'PECA B',
            ],
        ], 20.00);

        $this->assertSame(20.0, $rateio['desconto_aplicado']);

        $itens = [];
        foreach ($rateio['itens'] as $idx => $row) {
            $tipo = (string) ($row['tipo'] ?? '');
            $total = ErpMoney::parseBr($row['total']);
            $desconto = ErpMoney::parseBr($row['desconto']);
            $preco = ErpMoney::parseBr($row['preco']);
            $qtd = ErpMoney::parseBr($row['qtd'], 3);

            if ($tipo === 'P') {
                $itens[] = $this->peca(
                    (int) $row['product_id'],
                    (string) $row['descricao'],
                    $qtd,
                    $preco,
                    $desconto,
                    $total,
                );
            } else {
                $itens[] = $this->servico($idx + 100, (string) $row['descricao'], $qtd, $preco, $desconto, $total);
            }
        }

        $ordem = $this->ordemComItens($itens);
        // Simula cabeçalho com vl_desc ainda preenchido (web) — NFC-e não pode descontar de novo.
        $ordem->forceFill([
            'vl_desc_pecas' => 15.00,
            'vl_desc_servicos' => 5.00,
            'subtotal_pecas' => 150.00,
            'total_produtos' => 135.00,
        ]);

        $conf = (new NfceOrdemServicoService)->conferirPecas($ordem);

        $this->assertCount(2, $conf['itens']);
        $somaPecas = round(array_sum(array_map(
            static fn (array $l): float => $l['total'],
            $conf['itens'],
        )), 2);

        // Rateio 20 em base 200: peça A 10, serviço 5, peça B 5 → peças líquidas 90+45=135.
        $this->assertEqualsWithDelta(135.00, $somaPecas, 0.001);
        $this->assertEqualsWithDelta(135.00, $conf['total'], 0.001);
        $this->assertEqualsWithDelta(15.00, $conf['desconto_pecas'], 0.001);
        // Não reaplica vl_desc_pecas (evita desconto em dobro).
        $this->assertEqualsWithDelta($conf['total'], $somaPecas, 0.001);
        $this->assertLessThan(150.00, $conf['total']);
        $this->assertEqualsWithDelta(
            round($conf['subtotal'] - $conf['desconto'] + $conf['acrescimo'], 2),
            $conf['total'],
            0.001,
        );
    }

    public function test_espelho_os108_sem_desconto_bate_totais_das_pecas(): void
    {
        // Espelho da OS 108 (DEV): 8 peças + 2 serviços, sem descontos.
        $ordem = $this->ordemComItens([
            $this->servico(8, 'MAO DE OBRA', 1, 580, 0, 580),
            $this->peca(150, 'PASTILHA FREIO TRASEIRA', 1, 168, 0, 168),
            $this->peca(5, 'BIELETA ESTABILIZADORA', 2, 102.95, 0, 205.90),
            $this->peca(6, 'BUCHA DA BARRA ESTABILIZADORA DIANTEIRA', 2, 49, 0, 98),
            $this->peca(19, 'PASTILHA DE FREIO DIANTEIRA', 1, 229, 0, 229),
            $this->peca(122, 'FILTRO DE CAMBIO', 1, 289, 0, 289),
            $this->peca(11, 'OLEO DE CAMBIO', 9, 118, 0, 1062),
            $this->peca(21, 'FLUSH', 1, 165, 0, 165),
            $this->peca(189, 'LUBEGARD CVT', 1, 268, 0, 268),
            $this->servico(80, 'MAO DE OBRA', 1, 400, 0, 400),
        ]);

        $conf = (new NfceOrdemServicoService)->conferirPecas($ordem);

        $this->assertCount(8, $conf['itens']);
        $this->assertEqualsWithDelta(2484.90, $conf['subtotal'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto_pecas'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['desconto'], 0.001);
        $this->assertEqualsWithDelta(0.0, $conf['acrescimo'], 0.001);
        $this->assertEqualsWithDelta(2484.90, $conf['total'], 0.001);
        $this->assertEqualsWithDelta(102.95, $conf['itens'][1]['valor_unitario'], 0.001);
        $this->assertEqualsWithDelta(205.90, $conf['itens'][1]['total'], 0.001);
    }

    /**
     * @param  list<OrdemServicoItem>  $itens
     */
    private function ordemComItens(array $itens): OrdemServico
    {
        $ordem = new OrdemServico();
        $ordem->setRelation('itens', new Collection($itens));

        return $ordem;
    }

    private function peca(
        int $productId,
        string $descricao,
        float $qtd,
        float $preco,
        float $desconto,
        float $total,
    ): OrdemServicoItem {
        $item = new OrdemServicoItem([
            'tipo' => 'P',
            'product_id' => $productId,
            'qtd' => $qtd,
            'preco' => $preco,
            'desconto' => $desconto,
            'acrescimo' => 0,
            'total' => $total,
        ]);
        $item->setRelation('product', new Product([
            'id' => $productId,
            'codigo' => 'P'.$productId,
            'descricao' => $descricao,
            'unidade' => 'UN',
        ]));

        return $item;
    }

    private function servico(
        int $id,
        string $descricao,
        float $qtd,
        float $preco,
        float $desconto,
        float $total,
    ): OrdemServicoItem {
        return new OrdemServicoItem([
            'tipo' => 'S',
            'product_id' => null,
            'qtd' => $qtd,
            'preco' => $preco,
            'desconto' => $desconto,
            'acrescimo' => 0,
            'total' => $total,
            'discriminacao' => $descricao,
        ]);
    }
}
