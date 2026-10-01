<?php

namespace Tests\Unit;

use App\Support\Erp\Import\RelatorioProdutosPdfParser;
use PHPUnit\Framework\TestCase;

class RelatorioProdutosPdfParserTest extends TestCase
{
    public function test_importa_ean_completo_e_pula_quebrado_e_sem_ean(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
123199 7896045103003 3 coraçoes -20 9,99 -199,80
123265 78962949019
93 7896294901993 -59 9,99 -589,41
122841 38 ABACAXI 9,99 0,00
121881 7891050000460 WHISKY NATU NOBILIS GF 1L -3 69,99 -209,97
123427 7896098900413 ype amaciante roupas 500 ml -1 8,09 9,99 -8,09 -9,99
TOTAL: 5 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto);

        $eans = array_column($out['importar'], 'ean');
        $this->assertSame(['7896045103003', '7891050000460', '7896098900413'], $eans);
        $this->assertEqualsWithDelta(9.99, $out['importar'][0]['preco'], 0.001);
        $this->assertEqualsWithDelta(69.99, $out['importar'][1]['preco'], 0.001);
        $this->assertEqualsWithDelta(9.99, $out['importar'][2]['preco'], 0.001);

        $motivos = array_column($out['pulados'], 'motivo');
        $this->assertContains('ean_quebrado', $motivos);
        $this->assertContains('sem_ean', $motivos);
    }

    public function test_nao_pega_valor_total_nem_inteiro_da_descricao(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
120876 7622210575999 361451 BIS LAKA FLOWPACK MI 1 00 01X100 8 -402 9,99 -4.015,98
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('7622210575999', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(9.99, $out['importar'][0]['preco'], 0.001);
        $this->assertStringContainsString('01X100', $out['importar'][0]['descricao']);
    }

    public function test_fase2_junta_ean_partido_mesma_linha(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
123265 78962949019
93 7896294901993 -59 9,99 -589,41
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('7896294901993', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(9.99, $out['importar'][0]['preco'], 0.001);
    }

    public function test_fase2_junta_ean_partido_multi_linha(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
123650
78903001268
99
ACHOCOLATADO FRITZ E FRIDA 400GR	-2	8,49	-16,98
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('7890300126899', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(8.49, $out['importar'][0]['preco'], 0.001);
        $this->assertStringContainsString('ACHOCOLATADO', $out['importar'][0]['descricao']);
    }

    public function test_fase2_aceita_sem_ean(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
122841 38 ABACAXI 9,99 0,00
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(9.99, $out['importar'][0]['preco'], 0.001);
        $this->assertStringContainsString('ABACAXI', $out['importar'][0]['descricao']);
    }

    public function test_fase2_inclui_ean_completo_para_preencher_lacunas(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
123199 7896045103003 3 coraçoes -20 9,99 -199,80
122841 38 ABACAXI 9,99 0,00
TOTAL: 2 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(2, $out['importar']);
        $eans = array_column($out['importar'], 'ean');
        $this->assertSame(['7896045103003', ''], $eans);
    }
}
