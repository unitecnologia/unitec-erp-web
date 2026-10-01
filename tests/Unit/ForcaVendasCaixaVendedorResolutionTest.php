<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasCaixaVendedorResolutionTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_vendedor_com_caixa_padrao_usa_caixa_conta_user(): void
    {
        [$empresa, $vendedor, $caixaPadrao] = $this->cenarioAlencar(pivotCaixaId: null);

        $resolvido = $vendedor->caixaContaDaEmpresa((int) $empresa->id);

        $this->assertNotNull($resolvido);
        $this->assertSame((int) $caixaPadrao->id, (int) $resolvido->id);
    }

    public function test_pivot_antigo_divergente_cede_ao_caixa_padrao_do_usuario(): void
    {
        [$empresa, $vendedor, $caixaAlencar, $caixaUsuario] = $this->cenarioAlencarComPivotDivergente();

        $resolvido = $vendedor->fresh(['empresas', 'usuario'])->caixaContaDaEmpresa((int) $empresa->id);

        $this->assertSame((int) $caixaAlencar->id, (int) $resolvido?->id);
        $this->assertNotSame((int) $caixaUsuario->id, (int) $resolvido?->id);
    }

    public function test_sem_caixa_padrao_usa_primeiro_liberado_do_usuario(): void
    {
        $empresa = $this->empresa();
        $caixaA = $this->caixaPdv('CAIXA A', 10);
        $caixaB = $this->caixaPdv('CAIXA B', 20);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-SEM-PAD',
            'nome' => 'SEM PADRAO',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);
        $vendedor->empresas()->attach($empresa->id, ['caixa_conta_id' => $caixaB->id]);

        $user = User::factory()->create([
            'vendedor_id' => $vendedor->id,
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        // Dois liberados, nenhum is_padrao → ordem por caixa_conta_id (igual accessibleCaixaContaIds).
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

        $resolvido = $vendedor->fresh(['empresas', 'usuario'])->caixaContaDaEmpresa((int) $empresa->id);

        // orderByDesc(is_padrao)->orderBy(caixa_conta_id) → menor id entre os liberados.
        $esperado = min((int) $caixaA->id, (int) $caixaB->id);
        $this->assertSame($esperado, (int) $resolvido?->id);
    }

    public function test_sem_vinculo_usuario_usa_pivot_legado(): void
    {
        $empresa = $this->empresa();
        $caixaPivot = $this->caixaPdv('PIVOT LEGADO', 30);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-LEGADO',
            'nome' => 'SO PIVOT',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);
        $vendedor->empresas()->attach($empresa->id, ['caixa_conta_id' => $caixaPivot->id]);

        $resolvido = $vendedor->fresh(['empresas', 'usuario'])->caixaContaDaEmpresa((int) $empresa->id);

        $this->assertSame((int) $caixaPivot->id, (int) $resolvido?->id);
    }

    public function test_operador_do_monitor_nao_interfere_no_resolve(): void
    {
        [$empresa, $vendedor, $caixaAlencar, $caixaUsuario] = $this->cenarioAlencarComPivotDivergente();

        $operador = User::factory()->create([
            'name' => 'OPERADOR MONITOR',
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'vendedor_id' => null,
        ]);
        DB::table('caixa_conta_user')->insert([
            'user_id' => $operador->id,
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $caixaUsuario->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::login($operador);

        $order = ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_uuid' => 'test-device',
            'user_id' => $operador->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'vendedor_id' => $vendedor->id,
            'empresa_id' => $empresa->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'payload' => ['caixa_id' => $caixaUsuario->id],
            'received_at' => now(),
        ]);

        $resolvido = $this->resolveCaixaContaId($order);

        $this->assertSame((int) $caixaAlencar->id, $resolvido);
        $this->assertNotSame((int) $caixaUsuario->id, $resolvido);
        $this->assertSame((int) $operador->id, (int) Auth::id());
    }

    public function test_fallback_payload_quando_vendedor_sem_caixa(): void
    {
        $empresa = $this->empresa();
        $caixaPayload = $this->caixaPdv('PAYLOAD', 40);
        CaixaConta::ensureCaixaGeral();

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-SEM',
            'nome' => 'SEM CAIXA',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);
        // Sem usuário e sem pivot.

        $order = ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_uuid' => 'test-device',
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'vendedor_id' => $vendedor->id,
            'empresa_id' => $empresa->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'payload' => ['caixa_id' => $caixaPayload->id],
            'received_at' => now(),
        ]);

        $this->assertSame((int) $caixaPayload->id, $this->resolveCaixaContaId($order));
    }

    public function test_fallback_caixa_geral_sem_vendedor_nem_payload(): void
    {
        $geral = CaixaConta::ensureCaixaGeral();

        $order = ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_uuid' => 'test-device',
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'vendedor_id' => null,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'payload' => [],
            'received_at' => now(),
        ]);

        $this->assertSame((int) $geral->id, $this->resolveCaixaContaId($order));
    }

    public function test_troca_padrao_em_permissoes_vale_no_proximo_resolve_sem_reeditar_vendedor(): void
    {
        [$empresa, $vendedor, $caixaAlencar, $caixaUsuario] = $this->cenarioAlencarComPivotDivergente();
        $user = $vendedor->usuario;

        $this->assertSame((int) $caixaAlencar->id, (int) $vendedor->caixaContaDaEmpresa((int) $empresa->id)?->id);

        DB::table('caixa_conta_user')
            ->where('user_id', $user->id)
            ->where('empresa_id', $empresa->id)
            ->update(['is_padrao' => false]);

        DB::table('caixa_conta_user')->insert([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $caixaUsuario->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pivot permanece em ALENCAR antigo (na verdade pivot = caixaUsuario no cenario);
        // o que importa: novo is_padrao deve valer imediatamente.
        $resolvido = $vendedor->fresh(['empresas', 'usuario'])->caixaContaDaEmpresa((int) $empresa->id);

        $this->assertSame((int) $caixaUsuario->id, (int) $resolvido?->id);
    }

    /**
     * @return array{0: Empresa, 1: Vendedor, 2: CaixaConta}
     */
    private function cenarioAlencar(?int $pivotCaixaId): array
    {
        $empresa = $this->empresa();
        $caixa = $this->caixaPdv('ALENCAR', 3);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-ALENCAR',
            'nome' => 'ALENCAR',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);
        $vendedor->empresas()->attach($empresa->id, [
            'caixa_conta_id' => $pivotCaixaId,
        ]);

        $user = User::factory()->create([
            'name' => 'ALENCAR',
            'vendedor_id' => $vendedor->id,
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        DB::table('caixa_conta_user')->insert([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $caixa->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$empresa, $vendedor->fresh(['empresas', 'usuario']), $caixa];
    }

    /**
     * @return array{0: Empresa, 1: Vendedor, 2: CaixaConta, 3: CaixaConta}
     */
    private function cenarioAlencarComPivotDivergente(): array
    {
        $empresa = $this->empresa();
        $caixaAlencar = $this->caixaPdv('ALENCAR', 3);
        $caixaUsuario = $this->caixaPdv('USUARIO', 2);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'V-ALENCAR-2',
            'nome' => 'ALENCAR',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);
        // Pivot antigo divergente (= caso real).
        $vendedor->empresas()->attach($empresa->id, ['caixa_conta_id' => $caixaUsuario->id]);

        $user = User::factory()->create([
            'name' => 'ALENCAR',
            'vendedor_id' => $vendedor->id,
            'empresa_id' => $empresa->id,
            'is_admin' => false,
        ]);

        DB::table('caixa_conta_user')->insert([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $caixaAlencar->id,
            'is_padrao' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $empresa,
            $vendedor->fresh(['empresas', 'usuario']),
            $caixaAlencar,
            $caixaUsuario,
        ];
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'nome' => 'EMPRESA TESTE CAIXA '.Str::random(4),
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

    private function resolveCaixaContaId(ForcaVendasOrder $order): int
    {
        $svc = app(ForcaVendasFaturamentoService::class);
        $ref = new ReflectionMethod($svc, 'resolveCaixaContaId');
        $ref->setAccessible(true);

        return (int) $ref->invoke($svc, $order);
    }
}
