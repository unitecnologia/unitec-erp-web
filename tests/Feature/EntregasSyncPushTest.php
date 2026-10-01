<?php

namespace Tests\Feature;

use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\Empresa;
use App\Models\EntregasDevice;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EntregasSyncPushTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_push_grava_entrega_com_foto_e_e_idempotente_por_uuid(): void
    {
        Storage::fake('public');

        [$user, $device, $carga, $pedido] = $this->seedCarga();

        Sanctum::actingAs($user);
        $uuid = (string) Str::uuid();
        $foto = UploadedFile::fake()->image('comprovante.jpg', 640, 480);

        $first = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => $uuid,
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'observacao' => 'Deixado na portaria',
                'concluida_em' => now()->toIso8601String(),
                'foto' => $foto,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('duplicated', false)
            ->assertJsonPath('entrega.app_local_uuid', $uuid);

        $this->assertSame(1, CargaEntrega::query()->count());
        $this->assertNotEmpty($first->json('entrega.foto_path'));
        Storage::disk('public')->assertExists($first->json('entrega.foto_path'));

        // Reenvio do mesmo UUID não duplica.
        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => $uuid,
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'foto' => UploadedFile::fake()->image('outra.jpg'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('duplicated', true);

        $this->assertSame(1, CargaEntrega::query()->count());
    }

    public function test_push_nao_duplica_mesmo_pedido_com_uuid_diferente(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'foto' => UploadedFile::fake()->image('a.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'foto' => UploadedFile::fake()->image('b.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('duplicated', true);

        $this->assertSame(1, CargaEntrega::query()->count());
    }

    public function test_push_entregue_sem_foto_nem_assinatura(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'observacao' => 'Sem comprovante',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('entrega.status', 'entregue')
            ->assertJsonPath('entrega.foto_path', null)
            ->assertJsonPath('entrega.assinatura_path', null);

        $this->assertSame(1, CargaEntrega::query()->count());
    }

    public function test_push_entregue_com_assinatura(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $resp = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'assinatura' => UploadedFile::fake()->image('assinatura.png', 400, 120),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $path = $resp->json('entrega.assinatura_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_push_entregue_parcial_com_itens(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $itens = json_encode([
            [
                'produto_id' => 1,
                'codigo' => 'PP1',
                'descricao' => 'PROD PUSH',
                'unidade' => 'UN',
                'quantidade_original' => 2,
                'quantidade' => 1,
            ],
        ], JSON_THROW_ON_ERROR);

        $resp = $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'parcial',
                'itens' => $itens,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('entrega.status', 'parcial');

        $this->assertCount(1, $resp->json('entrega.itens'));
        $this->assertSame(1, \App\Models\CargaEntregaItem::query()->count());
    }

    public function test_push_nao_entregue_sem_foto_com_motivo(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $uuid = (string) Str::uuid();

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => $uuid,
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'nao_entregue',
                'motivo_nao_entrega' => CargaEntrega::MOTIVO_CLIENTE_FECHADO,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('entrega.status', 'nao_entregue')
            ->assertJsonPath('entrega.motivo_nao_entrega', CargaEntrega::MOTIVO_CLIENTE_FECHADO);

        $this->assertSame(1, CargaEntrega::query()->count());

        // Não permite segunda conclusão (mesmo com status diferente).
        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'foto' => UploadedFile::fake()->image('depois.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('duplicated', true);

        $this->assertSame(1, CargaEntrega::query()->count());
    }

    public function test_push_nao_entregue_outro_exige_observacao(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();
        Sanctum::actingAs($user);

        $this->withHeader('X-ENT-Device', $device->device_uuid)
            ->postJson('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'nao_entregue',
                'motivo_nao_entrega' => CargaEntrega::MOTIVO_OUTRO,
            ])
            ->assertStatus(422);
    }

    public function test_push_bloqueia_carga_de_outro_entregador(): void
    {
        Storage::fake('public');
        [$user, $device, $carga, $pedido] = $this->seedCarga();

        $outro = User::factory()->create([
            'empresa_id' => $user->empresa_id,
            'senha_app_forca_vendas' => 'x',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);
        $deviceOutro = EntregasDevice::query()->create([
            'device_uuid' => 'ent-push-outro',
            'status' => EntregasDevice::STATUS_APROVADO,
            'approved_at' => now(),
            'empresa_id' => $user->empresa_id,
            'user_id' => $outro->id,
        ]);

        Sanctum::actingAs($outro);

        $this->withHeader('X-ENT-Device', $deviceOutro->device_uuid)
            ->post('/api/v1/entregas/sync/push', [
                'app_local_uuid' => (string) Str::uuid(),
                'carga_id' => $carga->id,
                'pedido_id' => $pedido->id,
                'status' => 'entregue',
                'foto' => UploadedFile::fake()->image('x.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertJsonPath('code', 'entregador_mismatch');
    }

    /**
     * @return array{0: User, 1: EntregasDevice, 2: Carga, 3: Venda}
     */
    private function seedCarga(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '88',
            'nome' => 'EMPRESA PUSH',
            'fantasia' => 'Push Teste',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'ENTREGADOR PUSH',
            'senha_app_forca_vendas' => 'senha123',
            'acesso_app_entregas' => true,
            'ativo' => true,
        ]);

        $device = EntregasDevice::query()->create([
            'device_uuid' => 'ent-push-1',
            'status' => EntregasDevice::STATUS_APROVADO,
            'approved_at' => now(),
            'empresa_id' => $empresa->id,
            'user_id' => $user->id,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-PUSH-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE PUSH',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $produto = Product::query()->create([
            'codigo' => 'PP1',
            'descricao' => 'PROD PUSH',
            'unidade' => 'UN',
            'preco_venda' => 10,
            'estoque' => 0,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9901',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 100,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $pedido->id,
            'product_id' => $produto->id,
            'quantidade' => 1,
            'valor_item' => 100,
            'total' => 100,
        ]);

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '101',
            'data' => now()->toDateString(),
            'entregador_user_id' => $user->id,
            'status' => Carga::STATUS_FECHADA,
        ]);
        $carga->pedidos()->attach($pedido->id);

        return [$user, $device, $carga, $pedido];
    }
}
