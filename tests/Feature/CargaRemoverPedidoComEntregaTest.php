<?php

namespace Tests\Feature;

use App\Filament\Resources\CargaResource\Pages\ListCargas;
use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\CargaPedido;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class CargaRemoverPedidoComEntregaTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_remove_pedido_sem_entrega_normalmente(): void
    {
        [$user, $empresa, $carga, $pedido] = $this->seedCargaAbertaComPedido();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('editCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->call('syncPedidoCargaSelecionado', (int) $pedido->id, true)
            ->call('removerPedidosSelecionados')
            ->assertSet('cargaPedidoIds', [])
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', false);

        $this->assertSame(0, CargaPedido::query()->where('carga_id', $carga->id)->count());
    }

    public function test_nao_remove_pedido_com_carga_entrega(): void
    {
        [$user, $empresa, $carga, $pedido, $entrega] = $this->seedCargaAbertaComPedido(comEntrega: true);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('editCarga')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->call('syncPedidoCargaSelecionado', (int) $pedido->id, true)
            ->call('removerPedidosSelecionados')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->assertNotified();

        $this->assertSame(1, CargaPedido::query()->where('carga_id', $carga->id)->where('pedido_id', $pedido->id)->count());
        $this->assertTrue(CargaEntrega::query()->whereKey($entrega->id)->exists());
        $this->assertSame(CargaEntrega::STATUS_ENTREGUE, $entrega->fresh()->status);
    }

    public function test_save_nao_contorna_bloqueio_de_pedido_com_entrega(): void
    {
        [$user, $empresa, $carga, $pedido, $entrega] = $this->seedCargaAbertaComPedido(comEntrega: true);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('editCarga')
            ->set('cargaPedidoIds', [])
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertNotified();

        $this->assertSame(1, CargaPedido::query()->where('carga_id', $carga->id)->where('pedido_id', $pedido->id)->count());
        $this->assertTrue(CargaEntrega::query()->whereKey($entrega->id)->exists());
        $this->assertSame(
            (string) $entrega->app_local_uuid,
            (string) $entrega->fresh()->app_local_uuid,
        );
    }

    /**
     * @return array{0: User, 1: Empresa, 2: Carga, 3: Venda, 4?: CargaEntrega}
     */
    private function seedCargaAbertaComPedido(bool $comEntrega = false): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '91',
            'nome' => 'EMPRESA CARGA ENTREGA',
            'fantasia' => 'CE',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador Entrega',
            'is_admin' => true,
            'ativo' => true,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-CE-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Entrega',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9201',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 50,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
            'entregador_user_id' => $user->id,
        ]);
        $carga->pedidos()->attach([$pedido->id]);

        if (! $comEntrega) {
            return [$user, $empresa, $carga, $pedido];
        }

        $entrega = CargaEntrega::query()->create([
            'empresa_id' => $empresa->id,
            'carga_id' => $carga->id,
            'pedido_id' => $pedido->id,
            'entregador_user_id' => $user->id,
            'app_local_uuid' => (string) Str::uuid(),
            'status' => CargaEntrega::STATUS_ENTREGUE,
            'concluida_em' => now(),
        ]);

        return [$user, $empresa, $carga, $pedido, $entrega];
    }
}
