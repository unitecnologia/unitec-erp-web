<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class PdvCaixaPadraoResolutionTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_sem_vinculo_usa_caixa_geral_como_padrao(): void
    {
        $empresa = $this->empresa();
        $this->caixaPdv('CAIXA PDV 1', 10);
        $this->caixaPdv('CAIXA PDV 2', 20);
        $geral = CaixaConta::ensureCaixaGeral();

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        $this->assertSame((int) $geral->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
        $this->assertSame(CaixaConta::NOME_CAIXA_GERAL, $user->defaultCaixaContaNome((int) $empresa->id));
    }

    public function test_com_vinculo_respeita_caixa_padrao(): void
    {
        $empresa = $this->empresa();
        $caixaA = $this->caixaPdv('CAIXA A', 10);
        $caixaB = $this->caixaPdv('CAIXA B', 20);
        CaixaConta::ensureCaixaGeral();

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        DB::table('caixa_conta_user')->insert([
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaA->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaB->id,
                'is_padrao' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame((int) $caixaB->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
    }

    public function test_vinculo_sem_is_padrao_usa_primeiro_explicito_nao_outro_pdv(): void
    {
        $empresa = $this->empresa();
        $caixaA = $this->caixaPdv('CAIXA A', 10);
        $caixaB = $this->caixaPdv('CAIXA B', 20);
        $this->caixaPdv('CAIXA C NAO VINCULADO', 30);
        CaixaConta::ensureCaixaGeral();

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        DB::table('caixa_conta_user')->insert([
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaB->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => $caixaA->id,
                'is_padrao' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame((int) $caixaA->id, (int) $user->defaultCaixaContaId((int) $empresa->id));
    }

    public function test_sem_vinculo_ainda_pode_operar_pdv_por_compatibilidade(): void
    {
        $empresa = $this->empresa();
        $this->caixaPdv('CAIXA PDV 1', 10);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        $this->assertTrue($user->podeOperarComCaixaPdv((int) $empresa->id));
        $this->assertNotSame(
            (int) $user->accessibleCaixaContaIds((int) $empresa->id)[0],
            (int) $user->defaultCaixaContaId((int) $empresa->id),
        );
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'nome' => 'EMPRESA PDV CAIXA '.Str::random(4),
            'ativo' => true,
        ]);
    }

    private function caixaPdv(string $nome, int $codigo): CaixaConta
    {
        return CaixaConta::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => CaixaConta::TIPO_PDV,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }
}
