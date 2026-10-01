<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Support\Erp\EmpresaParametros;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaMonitorVendasExibirCustoProdutoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_false_grupo_monitor(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey('param_monitor_vendas_exibir_custo_produto', $fields);
        $this->assertFalse($fields['param_monitor_vendas_exibir_custo_produto']['default']);
        $this->assertSame(
            'Exibir custo dos produtos nas grades',
            $fields['param_monitor_vendas_exibir_custo_produto']['label'],
        );
        $this->assertSame(
            'Exibe o custo unitário atual dos produtos na Tela de Venda e no Monitor.',
            $fields['param_monitor_vendas_exibir_custo_produto']['hint'],
        );
        $this->assertSame(
            'monitor_vendas',
            EmpresaParametros::permissionGroupForField('param_monitor_vendas_exibir_custo_produto'),
        );
    }

    public function test_empresa_nova_tem_flag_false_e_coluna_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_custo_produto'));

        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ CUSTO GRADE',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->param_monitor_vendas_exibir_custo_produto);

        $empresa->update(['param_monitor_vendas_exibir_custo_produto' => true]);
        $this->assertTrue((bool) $empresa->fresh()->param_monitor_vendas_exibir_custo_produto);
    }

    public function test_fonte_custo_grade_reutiliza_resolver_da_margem_em_lote(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMP CUSTO GRADE',
            'ativo' => true,
        ]);

        $comPep = Product::query()->create([
            'codigo' => 'CG1',
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
            'codigo' => 'CG2',
            'descricao' => 'SO CUSTO',
            'preco_venda' => 80,
            'preco_custo' => 25,
            'preco_compra' => 20,
            'ativo' => true,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $map = ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            [$comPep->id, $soProduto->id],
            $empresa->id,
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(55.0, $map[$comPep->id]);
        $this->assertSame(25.0, $map[$soProduto->id]);
    }
}
