<?php

namespace Tests\Feature;

use App\Filament\Pages\GestaoEntregasPage;
use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class GestaoEntregasPageTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_lista_pendente_entregue_e_abre_detalhe(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => '77',
            'nome' => 'EMPRESA GESTAO ENTREGAS',
            'fantasia' => 'GE',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'João Entregador',
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);

        $cliente = Person::query()->create([
            'codigo' => 'C-GE-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Mercado Central',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $pedidoEntregue = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1250',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 100,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $pedidoPendente = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1252',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 50,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '15',
            'data' => now()->toDateString(),
            'entregador_user_id' => $user->id,
            'status' => Carga::STATUS_FECHADA,
        ]);
        $carga->pedidos()->attach([$pedidoEntregue->id, $pedidoPendente->id]);

        CargaEntrega::query()->create([
            'empresa_id' => $empresa->id,
            'carga_id' => $carga->id,
            'pedido_id' => $pedidoEntregue->id,
            'entregador_user_id' => $user->id,
            'app_local_uuid' => (string) Str::uuid(),
            'status' => CargaEntrega::STATUS_ENTREGUE,
            'observacao' => 'Recebido pelo responsável',
            'foto_path' => 'carga-entregas/15/1250/foto.jpg',
            'concluida_em' => now(),
        ]);

        $component = Livewire::test(GestaoEntregasPage::class)
            ->assertOk()
            ->assertSet('rows', fn (array $rows): bool => count($rows) === 2)
            ->call('abrirDetalhe', (int) $carga->id, (int) $pedidoEntregue->id)
            ->assertSet('detalheOpen', true)
            ->assertSet('detalhe.status', 'entregue')
            ->assertSet('detalhe.observacao', 'Recebido pelo responsável')
            ->assertSet('detalhe.cliente', 'Mercado Central');

        $statuses = collect($component->get('rows'))->pluck('status')->sort()->values()->all();
        $this->assertSame(['entregue', 'pendente'], $statuses);

        $component
            ->set('statusFilter', 'pendente')
            ->assertSet('rows', fn (array $rows): bool => count($rows) === 1 && ($rows[0]['status'] ?? null) === 'pendente');

        $component
            ->call('abrirCargaLookup')
            ->assertSet('cargaLookupOpen', true)
            ->assertSet('cargaLookupRows', fn (array $rows): bool => count($rows) >= 1 && ($rows[0]['numero'] ?? null) === '15')
            ->call('selecionarCargaLookup', '15')
            ->assertSet('cargaLookupOpen', false)
            ->assertSet('numeroCarga', '15');
    }
}
