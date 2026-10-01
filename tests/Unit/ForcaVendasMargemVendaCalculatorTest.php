<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasMargemVendaCalculatorTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_rateio_proporcional_com_sobra_no_ultimo(): void
    {
        $itens = [
            ['total' => 100.00],
            ['total' => 50.00],
            ['total' => 50.00],
        ];

        $rateio = ForcaVendasMargemVendaCalculator::ratearValorNosItens(10.00, 200.00, $itens);

        $this->assertSame(5.00, $rateio[0]);
        $this->assertSame(2.50, $rateio[1]);
        $this->assertSame(2.50, $rateio[2]);
        $this->assertEqualsWithDelta(10.00, array_sum($rateio), 0.001);
    }

    public function test_calculo_sem_desconto_cabecalho_bate_soma_itens(): void
    {
        $itens = [
            [
                'product_id' => 1,
                'codigo' => 'A',
                'descricao' => 'Produto A',
                'quantidade' => 2,
                'preco_unitario' => 50,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 100,
            ],
            [
                'product_id' => 2,
                'codigo' => 'B',
                'descricao' => 'Produto B',
                'quantidade' => 1,
                'preco_unitario' => 40,
                'desconto' => 5,
                'acrescimo' => 0,
                'total' => 35,
            ],
        ];

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $itens,
            0,
            0,
            [1 => 30.0, 2 => 20.0],
        );

        $somaLiquidos = array_sum(array_column($resultado['linhas'], 'liquido'));
        $this->assertEqualsWithDelta(135.00, $somaLiquidos, 0.001);
        $this->assertEqualsWithDelta(135.00, $resultado['totais']['venda_liquida'], 0.001);
        $this->assertEqualsWithDelta(80.00, $resultado['totais']['custo_total'], 0.001);
        $this->assertEqualsWithDelta(55.00, $resultado['totais']['lucro_estimado'], 0.001);
        $this->assertEqualsWithDelta(40.74, $resultado['totais']['margem'], 0.01);
    }

    public function test_desconto_cabecalho_rateado_soma_bate_total_liquido(): void
    {
        $itens = [
            [
                'product_id' => 1,
                'codigo' => 'A',
                'descricao' => 'A',
                'quantidade' => 1,
                'preco_unitario' => 100,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 100,
            ],
            [
                'product_id' => 2,
                'codigo' => 'B',
                'descricao' => 'B',
                'quantidade' => 1,
                'preco_unitario' => 50,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 50,
            ],
        ];

        $descontoCab = 15.00;
        $totalEsperado = 135.00;

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $itens,
            $descontoCab,
            0,
            [1 => 10.0, 2 => 10.0],
        );

        $soma = round(array_sum(array_column($resultado['linhas'], 'liquido')), 2);
        $this->assertSame($totalEsperado, $soma);
        $this->assertSame($totalEsperado, $resultado['totais']['venda_liquida']);
        $this->assertSame(90.00, $resultado['linhas'][0]['liquido']);
        $this->assertSame(45.00, $resultado['linhas'][1]['liquido']);
    }

    public function test_acrescimo_cabecalho_e_desconto_item(): void
    {
        $itens = [
            [
                'product_id' => 1,
                'codigo' => 'A',
                'descricao' => 'A',
                'quantidade' => 2,
                'preco_unitario' => 10,
                'desconto' => 2,
                'acrescimo' => 0,
                'total' => 18,
            ],
            [
                'product_id' => 2,
                'codigo' => 'B',
                'descricao' => 'B',
                'quantidade' => 1,
                'preco_unitario' => 20,
                'desconto' => 0,
                'acrescimo' => 3,
                'total' => 23,
            ],
        ];

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $itens,
            0,
            10.00,
            [1 => 5.0, 2 => 8.0],
        );

        $soma = round(array_sum(array_column($resultado['linhas'], 'liquido')), 2);
        $this->assertSame(51.00, $soma);
        $this->assertSame(51.00, $resultado['totais']['venda_liquida']);
        // Item 1: desconto de item (−2) + rateio de acréscimo de cabeçalho → Desc./Acr. líquido ≠ −2.
        $this->assertEqualsWithDelta(
            $resultado['linhas'][0]['liquido'] - $resultado['linhas'][0]['valor_vendido'],
            $resultado['linhas'][0]['desc_acr'],
            0.001,
        );
        $this->assertEqualsWithDelta(20.00, $resultado['linhas'][0]['valor_vendido'], 0.001);
        $this->assertGreaterThan(0, $resultado['linhas'][1]['desc_acr']);
    }

    public function test_custos_em_lote_pep_custo_depois_produto_depois_compra(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMP MARGEM CUSTO',
            'ativo' => true,
        ]);

        $comPep = Product::query()->create([
            'codigo' => 'MG1',
            'descricao' => 'COM PEP',
            'preco_venda' => 100,
            'preco_custo' => 40,
            'preco_compra' => 30,
            'ativo' => true,
        ]);
        ProductEmpresaPreco::query()->create([
            'product_id' => $comPep->id,
            'empresa_id' => $empresa->id,
            'preco_custo' => 55,
            'preco_compra' => 0,
            'pct_custos' => 0,
            'pct_lucro' => 0,
            'preco_venda' => 100,
            'preco_atacado' => 0,
            'preco_especial' => 0,
        ]);

        $soProduto = Product::query()->create([
            'codigo' => 'MG2',
            'descricao' => 'SO CUSTO',
            'preco_venda' => 80,
            'preco_custo' => 25,
            'preco_compra' => 20,
            'ativo' => true,
        ]);

        $soCompra = Product::query()->create([
            'codigo' => 'MG3',
            'descricao' => 'SO COMPRA',
            'preco_venda' => 60,
            'preco_custo' => 0,
            'preco_compra' => 18,
            'ativo' => true,
        ]);

        $semNada = Product::query()->create([
            'codigo' => 'MG4',
            'descricao' => 'ZERADO',
            'preco_venda' => 10,
            'preco_custo' => 0,
            'preco_compra' => 0,
            'ativo' => true,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $map = ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            [$comPep->id, $soProduto->id, $soCompra->id, $semNada->id],
            $empresa->id,
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(55.0, $map[$comPep->id]);
        $this->assertSame(25.0, $map[$soProduto->id]);
        $this->assertSame(18.0, $map[$soCompra->id]);
        $this->assertSame(0.0, $map[$semNada->id]);
    }

    public function test_calculo_nao_muta_estrutura_dos_itens_de_entrada(): void
    {
        $itens = [
            [
                'product_id' => 9,
                'codigo' => 'X',
                'descricao' => 'X',
                'quantidade' => 1,
                'preco_unitario' => 10,
                'desconto' => 1,
                'acrescimo' => 0,
                'total' => 9,
            ],
        ];
        $snapshot = $itens;

        ForcaVendasMargemVendaCalculator::calcular($itens, 2, 1, [9 => 3.0]);

        $this->assertSame($snapshot, $itens);
    }

    public function test_residual_cabecalho_desconto_nao_rateado_nas_linhas(): void
    {
        $residual = ForcaVendasMargemVendaCalculator::residualCabecalho(150.00, 130.00);

        $this->assertSame(20.00, $residual['desconto']);
        $this->assertSame(0.0, $residual['acrescimo']);

        $itens = [
            [
                'product_id' => 1,
                'codigo' => 'A',
                'descricao' => 'A',
                'quantidade' => 1,
                'preco_unitario' => 100,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 100,
            ],
            [
                'product_id' => 2,
                'codigo' => 'B',
                'descricao' => 'B',
                'quantidade' => 1,
                'preco_unitario' => 50,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 50,
            ],
        ];

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $itens,
            $residual['desconto'],
            $residual['acrescimo'],
            [1 => 40.0, 2 => 20.0],
        );

        $this->assertSame(130.00, $resultado['totais']['venda_liquida']);
        $this->assertEqualsWithDelta(70.00, $resultado['totais']['lucro_estimado'], 0.001);
    }

    public function test_residual_cabecalho_zero_quando_ja_nas_linhas(): void
    {
        $residual = ForcaVendasMargemVendaCalculator::residualCabecalho(200.00, 200.00);

        $this->assertSame(0.0, $residual['desconto']);
        $this->assertSame(0.0, $residual['acrescimo']);
    }
}
