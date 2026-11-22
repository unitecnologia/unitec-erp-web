<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaOrcamentoPermitirSeparadoresGrupoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_no_catalogo_default_false_grupo_orcamento(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey('param_orcamento_permitir_separadores_grupo', $fields);
        $this->assertFalse($fields['param_orcamento_permitir_separadores_grupo']['default']);
        $this->assertSame(
            'Permitir separadores de grupo no Orçamento',
            $fields['param_orcamento_permitir_separadores_grupo']['label'],
        );
        $this->assertSame(
            'orcamento',
            EmpresaParametros::permissionGroupForField('param_orcamento_permitir_separadores_grupo'),
        );
    }

    public function test_empresa_nova_tem_flag_false_e_coluna_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('empresas', 'param_orcamento_permitir_separadores_grupo'));

        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ ORC SEPARADOR',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->param_orcamento_permitir_separadores_grupo);

        $empresa->update(['param_orcamento_permitir_separadores_grupo' => true]);
        $this->assertTrue((bool) $empresa->fresh()->param_orcamento_permitir_separadores_grupo);
    }
}
