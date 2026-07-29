<?php

namespace Tests\Unit;

use App\Models\Entrega;
use App\Models\EntregaItem;
use App\Support\Erp\Reports\ExpedicaoSeparacaoReport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ExpedicaoSeparacaoReportTest extends TestCase
{
    public function test_ordenacao_por_localizacao_e_codigo(): void
    {
        $entrega = new Entrega([
            'numero' => '158',
            'cliente_nome' => 'Cliente Teste',
        ]);
        $entrega->setRelation('venda', null);

        $itemA = new EntregaItem([
            'codigo' => '2',
            'codigo_barras' => '111',
            'descricao' => 'Produto B',
            'localizacao' => 'B01',
            'quantidade_pedida' => 1,
        ]);
        $itemB = new EntregaItem([
            'codigo' => '1',
            'codigo_barras' => '222',
            'descricao' => 'Produto A',
            'localizacao' => 'A01',
            'quantidade_pedida' => 2,
        ]);

        $entrega->setRelation('itens', collect([$itemA, $itemB]));

        $linhas = ExpedicaoSeparacaoReport::buildLinhas(collect([$entrega]), 'alfabetica');

        $this->assertSame('A01', $linhas[0]['localizacao']);
        $this->assertSame('1', $linhas[0]['codigo']);
        $this->assertSame('B01', $linhas[1]['localizacao']);
    }

    public function test_separadores_por_corredor_quando_ordenacao_localizacao(): void
    {
        $entrega = new Entrega(['numero' => '161', 'cliente_nome' => 'Cliente']);
        $entrega->setRelation('venda', null);

        $itemCorredor2 = new EntregaItem([
            'codigo' => '13',
            'descricao' => 'Produto C2',
            'localizacao' => 'C:2/M:3/P:5/G:4',
            'quantidade_pedida' => 1,
        ]);
        $itemCorredor6 = new EntregaItem([
            'codigo' => '14',
            'descricao' => 'Produto C6',
            'localizacao' => 'C:6/M:8/P:8/G:8',
            'quantidade_pedida' => 1,
        ]);

        $entrega->setRelation('itens', collect([$itemCorredor6, $itemCorredor2]));

        $linhas = ExpedicaoSeparacaoReport::buildLinhas(collect([$entrega]), 'localizacao');

        $this->assertSame('corredor_sep', $linhas[0]['tipo']);
        $this->assertSame('CORREDOR 2', $linhas[0]['label']);
        $this->assertSame('item', $linhas[1]['tipo']);
        $this->assertSame('corredor_sep', $linhas[2]['tipo']);
        $this->assertSame('CORREDOR 6', $linhas[2]['label']);
        $this->assertSame('item', $linhas[3]['tipo']);
    }

    public function test_separadores_por_pedido_quando_ordenacao_pedido(): void
    {
        $entregaA = new Entrega(['id' => 10, 'numero' => '100', 'cliente_nome' => 'Cliente A']);
        $entregaA->setRelation('venda', null);
        $entregaA->setRelation('itens', collect([
            new EntregaItem([
                'codigo' => '1',
                'descricao' => 'Produto A',
                'localizacao' => 'A01',
                'quantidade_pedida' => 1,
            ]),
        ]));

        $entregaB = new Entrega(['id' => 20, 'numero' => '200', 'cliente_nome' => 'Cliente B']);
        $entregaB->setRelation('venda', null);
        $entregaB->setRelation('itens', collect([
            new EntregaItem([
                'codigo' => '2',
                'descricao' => 'Produto B',
                'localizacao' => 'B01',
                'quantidade_pedida' => 2,
            ]),
        ]));

        $linhas = ExpedicaoSeparacaoReport::buildLinhas(collect([$entregaB, $entregaA]), 'pedido');

        $this->assertSame('pedido_sep', $linhas[0]['tipo']);
        $this->assertSame('Pedido 100 — CLIENTE A', $linhas[0]['label']);
        $this->assertSame('item', $linhas[1]['tipo']);
        $this->assertSame('pedido_sep', $linhas[2]['tipo']);
        $this->assertSame('Pedido 200 — CLIENTE B', $linhas[2]['label']);
        $this->assertSame('item', $linhas[3]['tipo']);
    }

    public function test_sem_separadores_fora_da_ordenacao_localizacao(): void
    {
        $entrega = new Entrega(['numero' => '161', 'cliente_nome' => 'Cliente']);
        $entrega->setRelation('venda', null);
        $entrega->setRelation('itens', collect([
            new EntregaItem([
                'codigo' => '2',
                'descricao' => 'B',
                'localizacao' => 'C:2/M:1/P:1/G:1',
                'quantidade_pedida' => 1,
            ]),
            new EntregaItem([
                'codigo' => '1',
                'descricao' => 'A',
                'localizacao' => 'C:6/M:1/P:1/G:1',
                'quantidade_pedida' => 1,
            ]),
        ]));

        $linhas = ExpedicaoSeparacaoReport::buildLinhas(collect([$entrega]), 'codigo');

        $this->assertCount(2, $linhas);
        $this->assertSame('item', $linhas[0]['tipo']);
        $this->assertSame('item', $linhas[1]['tipo']);
    }

    public function test_totaliza_quantidade(): void
    {
        $linhas = [
            ['quantidade' => 1.0],
            ['quantidade' => 2.5],
        ];

        $totais = ExpedicaoSeparacaoReport::columnTotals($linhas, ExpedicaoSeparacaoReport::defaultColumns());

        $this->assertSame('TOTAIS', $totais['pedido']);
        $this->assertSame('3,50', $totais['quantidade']);
    }
}
