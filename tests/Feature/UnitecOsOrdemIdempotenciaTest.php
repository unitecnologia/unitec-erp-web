<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\UnitecOsDevice;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Builder;
use Tests\Concerns\MigratesSqliteMemory;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitecOsOrdemIdempotenciaTest extends TestCase
{
    use MigratesSqliteMemory;

    private const UUID_A = '11111111-1111-4111-8111-111111111111';

    private const UUID_B = '22222222-2222-4222-8222-222222222222';

    private const UUID_C = '33333333-3333-4333-8333-333333333333';

    public function test_cria_os_com_uuid_do_app(): void
    {
        $ctx = $this->contexto();

        $response = $this->postOs($ctx, self::UUID_A, 'device-a');

        $response->assertCreated()
            ->assertJsonPath('message', 'OS criada com sucesso.')
            ->assertJsonPath('data.numero_os', $response->json('data.numero_os'));

        $id = (int) $response->json('data.id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, OrdemServico::query()->where('app_local_uuid', self::UUID_A)->count());
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $id,
            'empresa_id' => $ctx['empresa']->id,
            'app_local_uuid' => self::UUID_A,
            'device_uuid' => 'device-a',
        ]);
        $this->assertNotSame('', (string) $response->json('data.numero_os'));
    }

    public function test_retry_do_mesmo_uuid_devolve_a_mesma_os(): void
    {
        $ctx = $this->contexto();

        $primeira = $this->postOs($ctx, self::UUID_A, 'device-a')->assertCreated();
        $segunda = $this->postOs($ctx, self::UUID_A, 'device-a');

        $segunda->assertOk()
            ->assertJsonPath('data.id', $primeira->json('data.id'))
            ->assertJsonPath('data.numero_os', $primeira->json('data.numero_os'))
            ->assertJsonPath('message', 'OS criada com sucesso.');

        $this->assertSame(1, OrdemServico::query()->where('app_local_uuid', self::UUID_A)->count());
    }

    public function test_outro_uuid_cria_nova_os(): void
    {
        $ctx = $this->contexto();

        $a = $this->postOs($ctx, self::UUID_A, 'device-a')->assertCreated();
        $b = $this->postOs($ctx, self::UUID_B, 'device-a')->assertCreated();

        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
        $this->assertSame(2, OrdemServico::query()->whereNotNull('app_local_uuid')->count());
    }

    public function test_dois_aparelhos_com_uuids_diferentes_criam_duas_os(): void
    {
        $ctxA = $this->contexto('device-a');
        $ctxB = $this->contexto('device-b', $ctxA['empresa'], $ctxA['vendedor']);

        $a = $this->postOs($ctxA, self::UUID_A, 'device-a')->assertCreated();
        $b = $this->postOs($ctxB, self::UUID_B, 'device-b')->assertCreated();

        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $a->json('data.id'),
            'device_uuid' => 'device-a',
        ]);
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $b->json('data.id'),
            'device_uuid' => 'device-b',
        ]);
    }

    public function test_colisao_simultanea_do_mesmo_uuid_devolve_a_mesma_os(): void
    {
        $ctx = $this->contexto();
        $primeira = $this->postOs($ctx, self::UUID_C, 'device-a')->assertCreated();

        $esconderPrimeiraBusca = true;
        OrdemServico::addGlobalScope('race-hide', function (Builder $builder) use (&$esconderPrimeiraBusca): void {
            if (! $esconderPrimeiraBusca) {
                return;
            }

            $temUuid = false;
            foreach ($builder->getQuery()->wheres ?? [] as $where) {
                $coluna = (string) ($where['column'] ?? '');
                if ($coluna === 'app_local_uuid' || str_ends_with($coluna, '.app_local_uuid')) {
                    $temUuid = true;
                    break;
                }
            }

            if ($temUuid) {
                $esconderPrimeiraBusca = false;
                $builder->whereRaw('0 = 1');
            }
        });

        try {
            $segunda = $this->postOs($ctx, self::UUID_C, 'device-a');
        } finally {
            OrdemServico::clearBootedModels();
        }

        $this->assertFalse($esconderPrimeiraBusca, 'A primeira busca precisava falhar para exercitar a colisão do índice UNIQUE.');

        $segunda->assertOk()
            ->assertJsonPath('data.id', $primeira->json('data.id'))
            ->assertJsonPath('data.numero_os', $primeira->json('data.numero_os'));

        $this->assertSame(1, OrdemServico::query()->where('app_local_uuid', self::UUID_C)->count());
    }

    public function test_post_sem_uuid_continua_criando_os_nova(): void
    {
        $ctx = $this->contexto();

        $primeira = $this->postOs($ctx, null, null)->assertCreated();
        $segunda = $this->postOs($ctx, null, null)->assertCreated();

        $this->assertNotSame($primeira->json('data.id'), $segunda->json('data.id'));
        $this->assertSame(2, OrdemServico::query()->whereNull('app_local_uuid')->count());
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $primeira->json('data.id'),
            'app_local_uuid' => null,
        ]);
    }

    public function test_mesmo_uuid_em_empresas_diferentes_nao_mistura(): void
    {
        $ctxA = $this->contexto('device-a');
        $ctxB = $this->contexto('device-b');

        $a = $this->postOs($ctxA, self::UUID_A, 'device-a')->assertCreated();
        $b = $this->postOs($ctxB, self::UUID_A, 'device-b')->assertCreated();

        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $a->json('data.id'),
            'empresa_id' => $ctxA['empresa']->id,
            'app_local_uuid' => self::UUID_A,
        ]);
        $this->assertDatabaseHas('ordens_servico', [
            'id' => $b->json('data.id'),
            'empresa_id' => $ctxB['empresa']->id,
            'app_local_uuid' => self::UUID_A,
        ]);
        $this->assertSame(
            1,
            OrdemServico::query()
                ->where('empresa_id', $ctxA['empresa']->id)
                ->where('app_local_uuid', self::UUID_A)
                ->count()
        );
    }

    /**
     * @param  array{user: User, device: UnitecOsDevice, empresa: Empresa, vendedor: Vendedor}  $ctx
     */
    private function postOs(array $ctx, ?string $uuid, ?string $deviceUuid): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($ctx['user']);

        $payload = [
            'cliente' => 'JOAO TESTE',
            'equipamento' => 'NOTEBOOK',
            'problema' => 'Nao liga',
        ];
        if ($uuid !== null) {
            $payload['app_local_uuid'] = $uuid;
        }
        if ($deviceUuid !== null) {
            $payload['device_uuid'] = $deviceUuid;
        }

        return $this->withHeader('X-OS-Device', $ctx['device']->device_uuid)
            ->postJson('/api/v1/unitec-os/ordens', $payload);
    }

    /**
     * @return array{user: User, device: UnitecOsDevice, empresa: Empresa, vendedor: Vendedor}
     */
    private function contexto(?string $deviceUuid = 'device-a', ?Empresa $empresa = null, ?Vendedor $vendedor = null): array
    {
        $empresa ??= Empresa::query()->create([
            'codigo' => (string) (Empresa::query()->count() + 1),
            'nome' => 'EMPRESA '.substr((string) $deviceUuid, -1),
        ]);

        $vendedor ??= Vendedor::query()->create([
            'codigo' => (string) (Vendedor::query()->count() + 1),
            'nome' => 'TECNICO TESTE',
            'ativo' => true,
            'empresa_id' => $empresa->id,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'vendedor_id' => $vendedor->id,
        ]);

        $device = UnitecOsDevice::query()->create([
            'device_uuid' => $deviceUuid.'-'.substr(md5((string) microtime(true)), 0, 8),
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'status' => UnitecOsDevice::STATUS_APROVADO,
            'approved_at' => now(),
        ]);

        return [
            'user' => $user,
            'device' => $device,
            'empresa' => $empresa,
            'vendedor' => $vendedor,
        ];
    }
}
