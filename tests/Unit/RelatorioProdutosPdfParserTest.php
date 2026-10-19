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

    public function test_importa_id_de_dois_digitos_e_ean_colado_na_descricao(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
82 47 78900066 CIGARRO MALBORO BLUE ICE -107 17,50 -1.872,50
1092 1057 89550320000805CHIP TIM -12 30,00 -360,00
TOTAL: 2 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(2, $out['importar']);
        $this->assertSame('78900066', $out['importar'][0]['ean']);
        $this->assertStringContainsString('CIGARRO', $out['importar'][0]['descricao']);
        $this->assertEqualsWithDelta(17.50, $out['importar'][0]['preco'], 0.001);
        $this->assertSame('89550320000805', $out['importar'][1]['ean']);
        $this->assertStringContainsString('CHIP TIM', $out['importar'][1]['descricao']);
        $this->assertEqualsWithDelta(30.00, $out['importar'][1]['preco'], 0.001);
    }

    public function test_separa_ean_partido_e_proximo_produto_na_continuacao(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
277 78910002501
74 7891000242483 CALDO GALINHA MAGGI 19G -1 3,80 -3,80
TOTAL: 2 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(2, $out['importar']);
        $this->assertSame('7891000250174', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(0.0, $out['importar'][0]['preco'], 0.001);
        $this->assertSame('7891000242483', $out['importar'][1]['ean']);
        $this->assertStringContainsString('CALDO GALINHA MAGGI', $out['importar'][1]['descricao']);
        $this->assertEqualsWithDelta(3.80, $out['importar'][1]['preco'], 0.001);
    }

    public function test_ignora_rodape_de_endereco_colado_no_preco(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
587 552 7896026850148 PAPEL HIGIENICO DUETTO FOLHA DUPLA NEUTRO 4UN -27 8,99 -242,73 RUA MONTE FRADE MACAÉ, 291 - MONTE ALEGRE - 88348-429 - CAMBORIU/SC
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('7896026850148', $out['importar'][0]['ean']);
        $this->assertEqualsWithDelta(8.99, $out['importar'][0]['preco'], 0.001);
        $this->assertStringContainsString('PAPEL HIGIENICO', $out['importar'][0]['descricao']);
        $this->assertStringNotContainsString('RUA MONTE', $out['importar'][0]['descricao']);
    }

    public function test_junta_ean_em_tres_linhas_e_descricao_com_preco_na_seguinte(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
2128
79084794044
79
ziggy burley mint	-4	14,00	-56,00
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('7908479404479', $out['importar'][0]['ean']);
        $this->assertStringContainsString('ziggy burley mint', $out['importar'][0]['descricao']);
        $this->assertEqualsWithDelta(14.00, $out['importar'][0]['preco'], 0.001);
    }

    public function test_nao_trata_linha_de_estoque_como_produto_novo(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
186 151 70847034803
ENERGETICO MONSTER ENERGY DRAGON ICE TEA
473ML
20	11,00	220,00
206 171 6740298416810 KALOUD	44,00	0,00
TOTAL: 2 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(2, $out['importar']);
        $this->assertSame('70847034803', $out['importar'][0]['ean']);
        $this->assertStringContainsString('ENERGETICO MONSTER', $out['importar'][0]['descricao']);
        $this->assertEqualsWithDelta(11.00, $out['importar'][0]['preco'], 0.001);
        $this->assertSame('6740298416810', $out['importar'][1]['ean']);
    }

    public function test_fase2_remove_codigo_interno_da_descricao_sem_ean(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
2207 2207 ESFIRRA GRANDE 13,00 0,00
TOTAL: 1 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(1, $out['importar']);
        $this->assertSame('', $out['importar'][0]['ean']);
        $this->assertSame('ESFIRRA GRANDE', $out['importar'][0]['descricao']);
        $this->assertEqualsWithDelta(13.00, $out['importar'][0]['preco'], 0.001);
    }

    public function test_ignora_coluna_codigo_longo_e_usa_so_cod_barras(): void
    {
        $texto = <<<'TXT'
Relatório de Produtos
ID Código Cód. de Barras Descrição Estoque Custo Valor Custo totalB Valor total
1722 79060297 7891079001004 NISSIN TURMA DA MÔNICA CARNE SUAVE -6 3,50 -21,00
1744 79060319 7892840824648 RUFFLES SABOR CEBOLA E SALSA 33G 4 6,00 24,00
495 460 7894900027013 COCA COLA 2 L -1096 13,50 -14.796,00
TOTAL: 3 registro(s)
TXT;

        $parser = new RelatorioProdutosPdfParser();
        $out = $parser->parseTexto($texto, 2);

        $this->assertCount(3, $out['importar']);
        $this->assertSame('7891079001004', $out['importar'][0]['ean']);
        $this->assertSame('NISSIN TURMA DA MÔNICA CARNE SUAVE', $out['importar'][0]['descricao']);
        $this->assertStringNotContainsString('79060297', $out['importar'][0]['descricao']);
        $this->assertEqualsWithDelta(3.50, $out['importar'][0]['preco'], 0.001);
        $this->assertSame('7892840824648', $out['importar'][1]['ean']);
        $this->assertStringContainsString('RUFFLES', $out['importar'][1]['descricao']);
        $this->assertSame('7894900027013', $out['importar'][2]['ean']);
        $this->assertSame('COCA COLA 2 L', $out['importar'][2]['descricao']);
    }
}
