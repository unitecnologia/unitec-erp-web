<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaOrcamentoPermitirVisualizarMargemTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_false_grupo_orcamento(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey('param_orcamento_permitir_visualizar_margem', $fields);
        $this->assertFalse($fields['param_orcamento_permitir_visualizar_margem']['default']);
        $this->assertSame(
            'Exibir “Mais opções” na Tela de Orçamento',
            $fields['param_orcamento_permitir_visualizar_margem']['label'],
        );
        $this->assertSame(
            'Quando habilitado, o Lançamento de Orçamento exibe “Mais opções” para mostrar/ocultar o custo dos produtos e a margem da venda. Desabilitado: nenhuma informação de custo/margem no orçamento. Independente do parâmetro da Tela de Venda e Monitor.',
            $fields['param_orcamento_permitir_visualizar_margem']['hint'],
        );
        $this->assertSame(
            'orcamento',
            EmpresaParametros::permissionGroupForField('param_orcamento_permitir_visualizar_margem'),
        );
        $this->assertArrayHasKey('orcamento', EmpresaParametros::permissionGroups());
        $this->assertSame('Orçamento', EmpresaParametros::permissionGroups()['orcamento']);
    }

    public function test_empresa_nova_tem_flag_false_e_coluna_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', 'param_orcamento_permitir_visualizar_margem'));

        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ ORC MARGEM',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->param_orcamento_permitir_visualizar_margem);

        $empresa->update(['param_orcamento_permitir_visualizar_margem' => true]);
        $this->assertTrue((bool) $empresa->fresh()->param_orcamento_permitir_visualizar_margem);

        $empresa->update(['param_orcamento_permitir_visualizar_margem' => false]);
        $this->assertFalse((bool) $empresa->fresh()->param_orcamento_permitir_visualizar_margem);
    }
}
