<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaMonitorVendasMaisOpcoesTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_false_grupo_monitor(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey('param_monitor_vendas_exibir_mais_opcoes', $fields);
        $this->assertFalse($fields['param_monitor_vendas_exibir_mais_opcoes']['default']);
        $this->assertSame(
            'Exibir “Mais opções” na Tela de Venda e Monitor',
            $fields['param_monitor_vendas_exibir_mais_opcoes']['label'],
        );
        $this->assertSame(
            'monitor_vendas',
            EmpresaParametros::permissionGroupForField('param_monitor_vendas_exibir_mais_opcoes'),
        );
        $this->assertArrayHasKey('monitor_vendas', EmpresaParametros::permissionGroups());
    }

    public function test_empresa_nova_tem_flag_false_e_coluna_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', 'param_monitor_vendas_exibir_mais_opcoes'));

        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ MAIS OPCOES',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->param_monitor_vendas_exibir_mais_opcoes);

        $empresa->update(['param_monitor_vendas_exibir_mais_opcoes' => true]);
        $this->assertTrue((bool) $empresa->fresh()->param_monitor_vendas_exibir_mais_opcoes);

        $empresa->update(['param_monitor_vendas_exibir_mais_opcoes' => false]);
        $this->assertFalse((bool) $empresa->fresh()->param_monitor_vendas_exibir_mais_opcoes);
    }
}
