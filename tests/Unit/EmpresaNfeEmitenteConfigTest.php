<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaNfeEmitenteConfigTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_parametro_existe_no_catalogo_com_default_false(): void
    {
        $fields = EmpresaParametros::permissionFields();

        $this->assertArrayHasKey('param_monitor_vendas_escolher_empresa_emitente_nfe', $fields);
        $this->assertFalse($fields['param_monitor_vendas_escolher_empresa_emitente_nfe']['default']);
        $this->assertSame(
            'Escolher empresa emitente da NF-e',
            $fields['param_monitor_vendas_escolher_empresa_emitente_nfe']['label'],
        );
    }

    public function test_empresa_nova_tem_flag_false_por_default(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ TESTE NFE EMIT',
            'fantasia' => 'MATRIZ',
            'razao_social' => 'MATRIZ TESTE LTDA',
            'ativo' => true,
        ]);

        $empresa->refresh();

        $this->assertFalse((bool) $empresa->param_monitor_vendas_escolher_empresa_emitente_nfe);
        $this->assertTrue(Schema::hasTable('empresa_nfe_emitente'));
        $this->assertCount(0, $empresa->nfeEmitentes);
    }

    public function test_ligar_desligar_parametro(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'MATRIZ FLAG',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => false,
        ]);

        $empresa->update(['param_monitor_vendas_escolher_empresa_emitente_nfe' => true]);
        $this->assertTrue((bool) $empresa->fresh()->param_monitor_vendas_escolher_empresa_emitente_nfe);

        $empresa->update(['param_monitor_vendas_escolher_empresa_emitente_nfe' => false]);
        $this->assertFalse((bool) $empresa->fresh()->param_monitor_vendas_escolher_empresa_emitente_nfe);
    }

    public function test_salvar_emitentes_sem_duplicar_e_remover_sem_afetar_empresa(): void
    {
        $matriz = Empresa::query()->create(['nome' => 'MATRIZ A', 'ativo' => true]);
        $e2 = Empresa::query()->create(['nome' => 'FILIAL 2', 'ativo' => true]);
        $e3 = Empresa::query()->create(['nome' => 'FILIAL 3', 'ativo' => true]);

        $matriz->nfeEmitentes()->sync([$e2->id, $e3->id]);

        $this->assertEqualsCanonicalizing(
            [(int) $e2->id, (int) $e3->id],
            $matriz->nfeEmitentes()->pluck('empresas.id')->map(fn ($id) => (int) $id)->all(),
        );

        // Segunda sync com o mesmo conteúdo não duplica.
        $matriz->nfeEmitentes()->sync([$e2->id, $e3->id]);
        $this->assertSame(2, (int) DB::table('empresa_nfe_emitente')->where('empresa_id', $matriz->id)->count());

        $this->expectException(QueryException::class);
        DB::table('empresa_nfe_emitente')->insert([
            'empresa_id' => $matriz->id,
            'emitente_empresa_id' => $e2->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_remover_emitente_nao_apaga_empresa(): void
    {
        $matriz = Empresa::query()->create(['nome' => 'MATRIZ B', 'ativo' => true]);
        $e2 = Empresa::query()->create(['nome' => 'FILIAL REM', 'ativo' => true]);
        $e3 = Empresa::query()->create(['nome' => 'FILIAL KEEP', 'ativo' => true]);

        $matriz->nfeEmitentes()->sync([$e2->id, $e3->id]);
        $matriz->nfeEmitentes()->sync([$e3->id]);

        $this->assertEqualsCanonicalizing(
            [(int) $e3->id],
            $matriz->nfeEmitentes()->pluck('empresas.id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertNotNull(Empresa::query()->find($e2->id));
        $this->assertNotNull(Empresa::query()->find($e3->id));
    }

    public function test_configuracao_de_uma_matriz_nao_vaza_para_outra(): void
    {
        $m1 = Empresa::query()->create(['nome' => 'MATRIZ 1', 'ativo' => true]);
        $m2 = Empresa::query()->create(['nome' => 'MATRIZ 2', 'ativo' => true]);
        $filial = Empresa::query()->create(['nome' => 'FILIAL X', 'ativo' => true]);

        $m1->nfeEmitentes()->sync([$filial->id]);

        $this->assertCount(1, $m1->fresh()->nfeEmitentes);
        $this->assertCount(0, $m2->fresh()->nfeEmitentes);
        $this->assertTrue($filial->fresh()->nfeEmitenteDe()->whereKey($m1->id)->exists());
        $this->assertFalse($filial->fresh()->nfeEmitenteDe()->whereKey($m2->id)->exists());
    }

    public function test_empresa_user_permanece_inalterada(): void
    {
        $matriz = Empresa::query()->create(['nome' => 'MATRIZ USER', 'ativo' => true]);
        $filial = Empresa::query()->create(['nome' => 'FILIAL USER', 'ativo' => true]);

        $user = User::factory()->create([
            'empresa_id' => $matriz->id,
            'ativo' => true,
        ]);
        $user->empresas()->sync([$matriz->id]);

        $antes = $user->empresas()->pluck('empresas.id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $matriz->nfeEmitentes()->sync([$filial->id]);
        $matriz->update(['param_monitor_vendas_escolher_empresa_emitente_nfe' => true]);

        $depois = $user->fresh()->empresas()->pluck('empresas.id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($antes, $depois);
        $this->assertFalse($user->empresas()->whereKey($filial->id)->exists());
    }

    public function test_servicos_de_emissao_nfe_nao_foram_alterados_nesta_fatia(): void
    {
        // Smoke: classes do fluxo de emissão continuam carregáveis e sem dependência do pivot.
        $this->assertTrue(class_exists(\App\Support\Erp\Nfe\NfeVendaMercadoriaService::class));
        $this->assertTrue(class_exists(\App\Support\Erp\Nfe\NfeVendaLoteEmissionService::class));
        $this->assertTrue(class_exists(\App\Support\Fiscal\NfeEmissionService::class));

        $ref = new \ReflectionMethod(\App\Support\Erp\Nfe\NfeVendaLoteEmissionService::class, 'criarRascunho');
        $params = array_map(fn (\ReflectionParameter $p) => $p->getName(), $ref->getParameters());
        $this->assertContains('empresaEmitenteId', $params);
        $param = $ref->getParameters()[2] ?? null;
        $this->assertNotNull($param);
        $this->assertTrue($param->isOptional());
        $this->assertNull($param->getDefaultValue());
    }
}
