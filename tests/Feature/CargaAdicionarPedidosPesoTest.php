<?php

namespace Tests\Feature;

use App\Filament\Resources\CargaResource\Pages\ListCargas;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\CargaRomaneioReport;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class CargaAdicionarPedidosPesoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_adicionar_pedidos_atualiza_carga_e_calcula_peso(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        $component = Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertSet('cargaPedidoIds', [])
            ->call('syncPedidoDisponivelSelecionado', (int) $pedido->id, true)
            ->assertSet('pedidosDisponiveisSelecionados', [(string) $pedido->id])
            ->call('adicionarPedidosSelecionados')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->assertSet('pedidosDisponiveisSelecionados', []);

        $this->assertSame(3.0, round((float) $component->instance()->cargaPesoTotal(), 3));
        $this->assertSame(1, $component->instance()->cargaQtdPedidos());
    }

    public function test_sync_pedido_disponivel_nao_remonta_lista(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->call('syncPedidoDisponivelSelecionado', (int) $pedido->id, true)
            ->assertSet('pedidosDisponiveisSelecionados', [(string) $pedido->id])
            ->call('syncPedidoDisponivelSelecionado', (int) $pedido->id, false)
            ->assertSet('pedidosDisponiveisSelecionados', []);
    }

    public function test_romaneio_calcular_peso_total_com_prefixo(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);

        $carga = \App\Models\Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '77',
            'data' => now()->toDateString(),
            'status' => \App\Models\Carga::STATUS_ABERTA,
        ]);
        $carga->pedidos()->attach([$pedido->id]);
        $carga->load('pedidos');

        $this->assertSame(3.0, round(CargaRomaneioReport::calcularPesoTotalKg($carga), 3));
    }

    public function test_limite_pedidos_disponiveis_pode_aumentar(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('pedidosDisponiveisLimite', 200)
            ->set('pedidosDisponiveisLimite', 500)
            ->assertSet('pedidosDisponiveisLimite', 500)
            ->set('pedidosDisponiveisLimite', 999)
            ->assertSet('pedidosDisponiveisLimite', 200);
    }

    public function test_pedidos_disponiveis_filtra_do_dia_1_ate_data_da_carga(): void
    {
        [$user, $empresa, $pedidoNoMes] = $this->seedPedidoComPeso();

        $cliente = Person::query()->whereKey($pedidoNoMes->cliente_id)->firstOrFail();

        $fora = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9102',
            'data' => now()->subMonths(2)->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        $hoje = now()->toDateString();
        $inicioMes = now()->copy()->startOfMonth()->toDateString();
        $fimMes = now()->copy()->endOfMonth()->toDateString();

        $lw = Livewire::test(ListCargas::class)
            ->call('createCarga');

        $this->assertSame($inicioMes, $lw->get('pedidosDisponiveisFiltroDe'));
        $this->assertSame($fimMes, $lw->get('pedidosDisponiveisFiltroAte'));

        $ids = collect($lw->instance()->pedidosDisponiveisRows())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $pedidoNoMes->id, $ids);
        $this->assertNotContains((int) $fora->id, $ids);

        $periodo = (new \ReflectionMethod($lw->instance(), 'pedidosDisponiveisPeriodo'));
        $periodo->setAccessible(true);
        [$de, $ate] = $periodo->invoke($lw->instance());

        $this->assertSame($inicioMes, $de);
        $this->assertSame($fimMes, $ate);
    }

    public function test_adicionar_pedido_por_numero_inclui_na_carga(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('cargaPedidoIds', [])
            ->set('cargaPedidoNumeroDigitar', (string) $pedido->numero)
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->assertSet('cargaPedidoNumeroDigitar', '');
    }

    public function test_adicionar_pedido_por_numero_fora_do_filtro_de_data(): void
    {
        [$user, $empresa, $pedidoNoMes] = $this->seedPedidoComPeso();
        $cliente = Person::query()->whereKey($pedidoNoMes->cliente_id)->firstOrFail();

        $fora = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9103',
            'data' => now()->subMonths(2)->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->set('cargaPedidoNumeroDigitar', '9103')
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [(int) $fora->id])
            ->assertSet('cargaPedidoNumeroDigitar', '');
    }

    public function test_adicionar_pedido_por_numero_ja_na_carga_nao_duplica(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->set('cargaPedidoNumeroDigitar', (string) $pedido->numero)
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->set('cargaPedidoNumeroDigitar', (string) $pedido->numero)
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->assertSet('cargaPedidoNumeroDigitar', '');
    }

    public function test_adicionar_pedido_por_numero_inexistente_nao_altera(): void
    {
        [$user, $empresa] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('cargaPedidoIds', [])
            ->set('cargaPedidoNumeroDigitar', '999999')
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [])
            ->assertSet('cargaPedidoNumeroDigitar', '999999');
    }

    public function test_adicionar_pedido_por_numero_encontra_com_zeros_a_esquerda(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();
        $pedido->update(['numero' => '000052']);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->assertSet('cargaPedidoIds', [])
            ->set('cargaPedidoNumeroDigitar', '52')
            ->call('adicionarPedidoPorNumero')
            ->assertSet('cargaPedidoIds', [(int) $pedido->id])
            ->assertSet('cargaPedidoNumeroDigitar', '');
    }

    public function test_nao_permite_mesmo_pedido_em_duas_cargas_abertas(): void
    {
        [$user, $empresa, $pedido] = $this->seedPedidoComPeso();

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        \App\Support\Erp\ErpContext::clearMemo();

        $data = now()->toDateString();

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->set('cargaForm.data', $data)
            ->set('cargaPedidoIds', [(int) $pedido->id])
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', false)
            ->assertNotified();

        $this->assertSame(1, \App\Models\Carga::query()->where('empresa_id', $empresa->id)->count());
        $this->assertSame(1, \App\Models\CargaPedido::query()->where('pedido_id', $pedido->id)->count());

        Livewire::test(ListCargas::class)
            ->call('createCarga')
            ->set('cargaForm.data', $data)
            ->set('cargaPedidoIds', [(int) $pedido->id])
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertNotified();

        $this->assertSame(1, \App\Models\Carga::query()->where('empresa_id', $empresa->id)->count());
        $this->assertSame(1, \App\Models\CargaPedido::query()->where('pedido_id', $pedido->id)->count());
    }

    /**
     * @return array{0: User, 1: Empresa, 2: Venda}
     */
    private function seedPedidoComPeso(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '89',
            'nome' => 'EMPRESA CARGA PESO',
            'fantasia' => 'CP',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador Carga',
            'is_admin' => true,
            'ativo' => true,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-CP-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Peso',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $produto = Product::query()->create([
            'codigo' => 'PESO1',
            'descricao' => 'PROD PESO',
            'unidade' => 'UN',
            'preco_venda' => 10,
            'estoque' => 0,
            'peso_kg' => 1.5,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9101',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 20,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $pedido->id,
            'product_id' => $produto->id,
            'quantidade' => 2,
            'valor_item' => 10,
            'total' => 20,
        ]);

        return [$user, $empresa, $pedido];
    }
}
