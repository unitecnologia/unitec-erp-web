<?php

namespace Tests\Feature;

use App\Filament\Resources\CargaResource\Pages\ListCargas;
use App\Models\Carga;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class CargaFecharEntregadorTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_fechar_carga_sem_entregador_permanece_aberta(): void
    {
        [$user, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('fecharCargaSelecionada')
            ->assertNotified()
            ->assertSet('selecionados', [(string) (int) $carga->id]);

        $this->assertSame(Carga::STATUS_ABERTA, $carga->fresh()->status);
    }

    public function test_fechar_carga_com_entregador_fecha(): void
    {
        [$user, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $carga->update(['entregador_user_id' => $user->id]);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('fecharCargaSelecionada')
            ->assertNotified()
            ->assertSet('selecionados', [])
            ->assertSet('highlightedRecordId', null)
            ->assertSee('Fechada');

        $this->assertSame(Carga::STATUS_FECHADA, $carga->fresh()->status);
    }

    public function test_edit_carga_aceita_checkbox_sem_highlight_previo(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->assertSet('selecionados', [(string) (int) $carga->id])
            ->call('editCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertSet('cargaModalRecordId', (int) $carga->id);
    }

    public function test_highlight_da_linha_nao_abre_carga_sem_checkbox(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);

        Livewire::test(ListCargas::class)
            ->set('highlightedRecordId', (int) $carga->id)
            ->set('selecionados', [])
            ->call('editCarga')
            ->assertSet('cargaModalOpen', false)
            ->assertNotified();
    }

    public function test_duas_cargas_marcadas_bloqueiam_edit_fechar_cancelar(): void
    {
        [, $cargaA] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $cargaB = Carga::query()->create([
            'empresa_id' => $cargaA->empresa_id,
            'numero' => '2',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $cargaA->id, true)
            ->call('syncSelecionado', (int) $cargaB->id, true)
            ->assertCount('selecionados', 2)
            ->call('editCarga')
            ->assertSet('cargaModalOpen', false)
            ->assertNotified()
            ->call('fecharCargaSelecionada')
            ->assertNotified()
            ->call('cancelarCargaSelecionada')
            ->assertNotified();

        $this->assertSame(Carga::STATUS_ABERTA, $cargaA->fresh()->status);
        $this->assertSame(Carga::STATUS_ABERTA, $cargaB->fresh()->status);
    }

    public function test_marcar_desmarcar_e_cliques_rapidos_terminam_no_estado_final(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', $id, true)
            ->assertSet('selecionados', [(string) $id])
            ->call('syncSelecionado', $id, false)
            ->assertSet('selecionados', [])
            ->call('syncSelecionado', $id, true)
            ->call('syncSelecionado', $id, true)
            ->call('syncSelecionado', $id, false)
            ->call('syncSelecionado', $id, false)
            ->assertSet('selecionados', []);
    }

    public function test_marcar_a_e_highlight_b_acoes_usam_somente_a(): void
    {
        [, $cargaA] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $cargaB = Carga::query()->create([
            'empresa_id' => $cargaA->empresa_id,
            'numero' => '2',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $cargaA->id, true)
            ->set('highlightedRecordId', (int) $cargaB->id)
            ->call('editCarga')
            ->assertSet('cargaModalOpen', true)
            ->assertSet('cargaModalRecordId', (int) $cargaA->id);
    }

    public function test_refresh_table_limpa_selecao(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->set('highlightedRecordId', (int) $carga->id)
            ->call('refreshTable')
            ->assertSet('selecionados', [])
            ->assertSet('highlightedRecordId', null)
            ->assertNotified()
            ->call('editCarga')
            ->assertSet('cargaModalOpen', false)
            ->assertNotified();
    }

    public function test_render_real_aplica_classe_da_linha_a_partir_de_selecionados(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->set('selecionados', [$id])
            ->call('resetTable')
            ->assertSet('selecionados', [$id])
            ->assertSeeHtml('erp-cargas-row--checked');
    }

    public function test_checkbox_wire_key_inclui_estado_marcado(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $key = (string) (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('refreshTable')
            ->assertSet('selecionados', [])
            ->assertSeeHtml('wire:key="carga-sel-'.$key.'-0"');
    }

    public function test_imprimir_romaneio_nao_limpa_selecionados(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->set('selecionados', [$id])
            ->call('imprimirRomaneioSelecionado')
            ->assertRedirect()
            ->assertSet('selecionados', [$id]);

        $this->assertSame(
            [$id],
            session(ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO)
        );
    }

    public function test_imprimir_pedidos_nao_limpa_selecionados(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->set('selecionados', [$id])
            ->call('imprimirPedidosSelecionados')
            ->assertSet('selecionados', [$id]);

        $this->assertSame(
            [$id],
            session(ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO)
        );
    }

    public function test_duas_marcadas_imprimir_romaneio_aceita_multiplos(): void
    {
        [, $cargaA] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $cargaB = Carga::query()->create([
            'empresa_id' => $cargaA->empresa_id,
            'numero' => '2',
            'data' => now()->toDateString(),
            'status' => Carga::STATUS_ABERTA,
        ]);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $cargaA->id, true)
            ->call('syncSelecionado', (int) $cargaB->id, true)
            ->call('imprimirRomaneioSelecionado')
            ->assertRedirect()
            ->assertSet('selecionados', [
                (string) (int) $cargaA->id,
                (string) (int) $cargaB->id,
            ]);
    }

    public function test_marcar_f4_depois_f6_usa_mesma_selecao(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->assertSet('selecionados', [$id])
            ->call('imprimirRomaneioSelecionado')
            ->assertRedirect();

        $this->assertSame([$id], session(ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO));

        // Simula Fechar da impressão: remount consome a sessão e restaura $selecionados.
        Livewire::test(ListCargas::class)
            ->assertSet('selecionados', [$id])
            ->assertSeeHtml('erp-cargas-row--checked')
            ->call('imprimirPedidosSelecionados')
            ->assertSet('selecionados', [$id]);
    }

    public function test_retorno_impressao_restaura_selecao_e_consome_sessao(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        session([ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO => [$id]]);

        Livewire::test(ListCargas::class)
            ->assertSet('selecionados', [$id])
            ->assertSeeHtml('erp-cargas-row--checked');

        $this->assertNull(session(ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO));
    }

    public function test_f5_limpa_sessao_de_retorno_impressao(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);
        $id = (string) (int) $carga->id;

        $component = Livewire::test(ListCargas::class)
            ->set('selecionados', [$id]);

        session([ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO => [$id]]);

        $component
            ->call('refreshTable')
            ->assertSet('selecionados', []);

        $this->assertNull(session(ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO));
    }

    public function test_gravar_carga_fecha_o_modal(): void
    {
        [, $carga] = $this->seedCargaAbertaComPedido(entregadorUserId: null);

        Livewire::test(ListCargas::class)
            ->call('syncSelecionado', (int) $carga->id, true)
            ->call('editCarga')
            ->assertSet('cargaModalOpen', true)
            ->set('cargaForm.data', now()->toDateString())
            ->call('saveCarga')
            ->assertSet('cargaModalOpen', false)
            ->assertSet('selecionados', [(string) (int) $carga->id]);
    }

    /**
     * @return array{0: User, 1: Carga}
     */
    private function seedCargaAbertaComPedido(?int $entregadorUserId): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => '88',
            'nome' => 'EMPRESA CARGA FECHAR',
            'fantasia' => 'CF',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Aline Entregas',
            'is_admin' => true,
            'ativo' => true,
            'acesso_app_entregas' => true,
            'senha_app_forca_vendas' => '1234',
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);

        $cliente = Person::query()->create([
            'codigo' => 'C-CF-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Carga',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $pedido = Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '9001',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 48,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $carga = Carga::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '1',
            'data' => now()->toDateString(),
            'entregador_user_id' => $entregadorUserId,
            'status' => Carga::STATUS_ABERTA,
        ]);
        $carga->pedidos()->attach([$pedido->id]);

        return [$user, $carga];
    }
}
