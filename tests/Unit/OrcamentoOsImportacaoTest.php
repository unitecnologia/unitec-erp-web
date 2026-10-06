<?php

namespace Tests\Unit;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Product;
use App\Support\Erp\Os\OrcamentoOsImportacao;
use Tests\TestCase;

class OrcamentoOsImportacaoTest extends TestCase
{
    public function test_classifica_peca_e_servico_e_nao_soma_desconto_geral_na_linha(): void
    {
        $peca = new Product([
            'codigo' => 'P1',
            'descricao' => 'PECA',
            'is_servico' => false,
        ]);
        $peca->id = 1;

        $servico = new Product([
            'codigo' => 'S1',
            'descricao' => 'SERVICO',
            'is_servico' => true,
        ]);
        $servico->id = 2;

        $itemPeca = new OrcamentoItem([
            'quantidade' => 2,
            'preco_unitario' => 50,
            'desconto' => 10,
            'total' => 90,
            'descricao' => 'PECA',
        ]);
        $itemPeca->product_id = 1;
        $itemPeca->setRelation('product', $peca);

        $itemServico = new OrcamentoItem([
            'quantidade' => 1,
            'preco_unitario' => 100,
            'desconto' => 0,
            'total' => 110,
            'descricao' => 'SERVICO',
        ]);
        $itemServico->product_id = 2;
        $itemServico->setRelation('product', $servico);

        $orcamento = new Orcamento([
            'desconto_valor' => 20,
            'total' => 180,
        ]);
        $orcamento->setRelation('itens', collect([$itemPeca, $itemServico]));

        $mapeado = (new OrcamentoOsImportacao())->mapear($orcamento);

        $this->assertSame('P', $mapeado['linhas'][0]['tipo']);
        $this->assertSame('S', $mapeado['linhas'][1]['tipo']);
        $this->assertSame(10.0, $mapeado['linhas'][0]['desconto']);
        $this->assertSame(0.0, $mapeado['linhas'][1]['desconto']);
        $this->assertSame(10.0, $mapeado['linhas'][1]['acrescimo']);
        $this->assertSame(9.0, $mapeado['vl_desc_pecas']);
        $this->assertSame(11.0, $mapeado['vl_desc_servicos']);
        $this->assertSame(20.0, round($mapeado['vl_desc_pecas'] + $mapeado['vl_desc_servicos'], 2));

        $totalOs = round(
            ($mapeado['linhas'][0]['total'] - $mapeado['vl_desc_pecas'])
            + ($mapeado['linhas'][1]['total'] - $mapeado['vl_desc_servicos']),
            2,
        );
        $this->assertSame(180.0, $totalOs);
    }

    public function test_desconto_geral_de_so_pecas_fica_inteiro_em_pecas(): void
    {
        $rateio = (new OrcamentoOsImportacao())->ratearDescontoGeral(15.5, 40, 0);

        $this->assertSame(15.5, $rateio['pecas']);
        $this->assertSame(0.0, $rateio['servicos']);
    }

    public function test_desconto_geral_de_so_servicos_fica_inteiro_em_servicos(): void
    {
        $rateio = (new OrcamentoOsImportacao())->ratearDescontoGeral(8, 0, 30);

        $this->assertSame(0.0, $rateio['pecas']);
        $this->assertSame(8.0, $rateio['servicos']);
    }
}
