<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\User;
use App\Support\Erp\Nfe\NfeMonitorEmitenteResolver;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeMonitorEmitenteResolverTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_flag_false_por_default(): void
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ FLAG OFF',
            'ativo' => true,
        ]);

        $this->assertFalse((new NfeMonitorEmitenteResolver())->flagAtivo($matriz));
    }

    public function test_flag_true_quando_ligado(): void
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ FLAG ON',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
        ]);

        $this->assertTrue((new NfeMonitorEmitenteResolver())->flagAtivo($matriz));
    }

    public function test_opcoes_pivot_intersect_acesso_intersect_ativas(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedMatrizComDuasEmitentes();

        $ids = array_map(
            'intval',
            array_column((new NfeMonitorEmitenteResolver())->opcoesParaUsuario($matriz, $user), 'id')
        );

        $this->assertSame([(int) $acessivel->id], $ids);
        $this->assertNotContains((int) $semAcesso->id, $ids);
    }

    public function test_empresa_inativa_nao_aparece(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedMatrizComDuasEmitentes();
        $acessivel->update(['ativo' => false]);

        $ids = array_column(
            (new NfeMonitorEmitenteResolver())->opcoesParaUsuario($matriz->fresh(), $user),
            'id'
        );

        $this->assertSame([], $ids);
    }

    public function test_empresa_fora_do_pivot_nao_aparece_mesmo_com_acesso(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedMatrizComDuasEmitentes();

        $foraPivot = Empresa::query()->create([
            'nome' => 'FORA DO PIVOT',
            'razao_social' => 'FORA DO PIVOT LTDA',
            'ativo' => true,
            'cnpj' => '11222333000181',
        ]);
        $user->empresas()->syncWithoutDetaching([
            (int) $matriz->id,
            (int) $acessivel->id,
            (int) $foraPivot->id,
        ]);

        $ids = array_map(
            'intval',
            array_column((new NfeMonitorEmitenteResolver())->opcoesParaUsuario($matriz, $user->fresh()), 'id')
        );

        $this->assertSame([(int) $acessivel->id], $ids);
        $this->assertNotContains((int) $foraPivot->id, $ids);
    }

    public function test_emitente_sem_acesso_e_rejeitada_no_servidor(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedMatrizComDuasEmitentes();
        $resolver = new NfeMonitorEmitenteResolver();

        $this->assertTrue($resolver->emitentePermitida((int) $acessivel->id, $matriz, $user));
        $this->assertFalse($resolver->emitentePermitida((int) $semAcesso->id, $matriz, $user));
        $this->assertFalse($resolver->emitentePermitida(999999, $matriz, $user));
    }

    public function test_opcoes_vazias_quando_sem_emitentes_configuradas(): void
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ SEM PIVOT',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $matriz->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $matriz->id]);

        $this->assertSame([], (new NfeMonitorEmitenteResolver())->opcoesParaUsuario($matriz, $user));
    }

    public function test_nenhuma_nfe_criada_pelo_resolver(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedMatrizComDuasEmitentes();
        $antes = Nfe::query()->count();

        (new NfeMonitorEmitenteResolver())->opcoesParaUsuario($matriz, $user);
        (new NfeMonitorEmitenteResolver())->emitentePermitida((int) $acessivel->id, $matriz, $user);

        $this->assertSame($antes, Nfe::query()->count());
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: Empresa, 3: User}
     */
    private function seedMatrizComDuasEmitentes(): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ',
            'razao_social' => 'MATRIZ LTDA',
            'fantasia' => 'MATRIZ',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
            'cnpj' => '00000000000191',
        ]);
        $acessivel = Empresa::query()->create([
            'nome' => 'EMITENTE ACESSIVEL',
            'razao_social' => 'EMITENTE ACESSIVEL LTDA',
            'fantasia' => 'ACESSIVEL',
            'ativo' => true,
            'cnpj' => '11244477700049',
        ]);
        $semAcesso = Empresa::query()->create([
            'nome' => 'EMITENTE SEM ACESSO',
            'razao_social' => 'EMITENTE SEM ACESSO LTDA',
            'fantasia' => 'SEM ACESSO',
            'ativo' => true,
            'cnpj' => '22233344400181',
        ]);

        $matriz->nfeEmitentes()->sync([(int) $acessivel->id, (int) $semAcesso->id]);

        $user = User::factory()->create([
            'empresa_id' => $matriz->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $matriz->id, (int) $acessivel->id]);

        return [$matriz->fresh(), $acessivel->fresh(), $semAcesso->fresh(), $user->fresh()];
    }
}
