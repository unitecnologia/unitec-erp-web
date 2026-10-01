<?php

namespace Tests\Feature;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Filament\Resources\NfeResource;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeMonitorEmitenteResolver;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class MonitorNfeEmitenteModalTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_flag_false_fluxo_antigo_redireciona_sem_abrir_modal(): void
    {
        [$matriz, $user] = $this->seedUsuarioComAcessoMonitor(flag: false);
        [, $order, $venda] = $this->seedPedidoFaturadoApto($matriz, $user);

        $this->assertFalse((new NfeMonitorEmitenteResolver())->flagAtivo($matriz));

        $this->mock(NfeVendaMercadoriaService::class, function ($mock): void {
            $mock->shouldReceive('motivoInaptoParaNfe')->andReturn(null);
        });

        $component = $this->livewireMonitor($user, $matriz);
        $component
            ->set('selecionados', [(string) (int) $order->id])
            ->call('emitirNfeDoBotao')
            ->assertSet('nfeEmitenteModalOpen', false)
            ->assertRedirect(NfeResource::getUrl('index').'?venda_id='.$venda->id);

        $this->assertSame(0, Nfe::query()->count());
    }

    public function test_flag_true_abre_modal_somente_com_emitente_acessivel(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedCenarioCompleto();

        $antes = Nfe::query()->count();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('abrirModalEmpresaEmitenteNfe', [55, 66], 'lote');

        $component->assertSet('nfeEmitenteModalOpen', true);
        $component->assertSet('nfeEmitentePendingMode', 'lote');
        $component->assertSet('nfeEmitentePendingVendaIds', [55, 66]);

        $opcoes = $component->get('nfeEmitenteOpcoes');
        $ids = array_map('intval', array_column($opcoes, 'id'));
        $this->assertSame([(int) $acessivel->id], $ids);
        $this->assertNotContains((int) $semAcesso->id, $ids);

        $component->assertSet('nfeEmpresaEmitenteId', (int) $acessivel->id);
        $this->assertSame($antes, Nfe::query()->count());
    }

    public function test_flag_true_emitir_nfe_abre_selecao_em_vez_de_redirect(): void
    {
        [$matriz, $acessivel, $semAcesso, $user] = $this->seedCenarioCompleto();
        [, $order] = $this->seedPedidoFaturadoApto($matriz, $user);

        $this->mock(NfeVendaMercadoriaService::class, function ($mock): void {
            $mock->shouldReceive('motivoInaptoParaNfe')->andReturn(null);
        });

        $component = $this->livewireMonitor($user, $matriz);
        $component
            ->set('selecionados', [(string) (int) $order->id])
            ->call('emitirNfeDoBotao')
            ->assertSet('nfeEmitenteModalOpen', true)
            ->assertNoRedirect();

        $ids = array_map('intval', array_column($component->get('nfeEmitenteOpcoes'), 'id'));
        $this->assertSame([(int) $acessivel->id], $ids);
        $this->assertNotContains((int) $semAcesso->id, $ids);
        $this->assertSame(0, Nfe::query()->count());
    }

    public function test_sem_emitente_valida_mostra_mensagem_e_nao_abre_modal(): void
    {
        [$matriz, $user] = $this->seedUsuarioComAcessoMonitor(flag: true);

        $component = $this->livewireMonitor($user, $matriz);
        $component
            ->call('abrirModalEmpresaEmitenteNfe', [10], 'single')
            ->assertSet('nfeEmitenteModalOpen', false)
            ->assertNotified();
    }

    public function test_confirmar_rejeita_empresa_sem_acesso_e_nao_cria_nfe(): void
    {
        [$matriz, , $semAcesso, $user] = $this->seedCenarioCompleto();

        $antes = Nfe::query()->count();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('abrirModalEmpresaEmitenteNfe', [77], 'single');
        $component->set('nfeEmpresaEmitenteId', (int) $semAcesso->id);
        $component->call('confirmarEmpresaEmitenteNfe');

        $component->assertSet('nfeEmitenteModalOpen', true);
        $component->assertNotified();
        $this->assertSame($antes, Nfe::query()->count());
    }

    public function test_cancelar_modal_limpa_selecao(): void
    {
        [$matriz, $acessivel, , $user] = $this->seedCenarioCompleto();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('abrirModalEmpresaEmitenteNfe', [88], 'single');
        $component->assertSet('nfeEmitenteModalOpen', true);
        $component->assertSet('nfeEmpresaEmitenteId', (int) $acessivel->id);

        $component->call('closeNfeEmitenteModal');

        $component->assertSet('nfeEmitenteModalOpen', false);
        $component->assertSet('nfeEmpresaEmitenteId', null);
        $component->assertSet('nfeEmitentePendingVendaIds', []);
        $component->assertSet('nfeEmitenteOpcoes', []);
    }

    public function test_confirmar_single_redireciona_com_empresa_emitente_id(): void
    {
        [$matriz, $acessivel, , $user] = $this->seedCenarioCompleto();

        $antes = Nfe::query()->count();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('abrirModalEmpresaEmitenteNfe', [99], 'single');
        $component->set('nfeEmpresaEmitenteId', (int) $acessivel->id);
        $component
            ->call('confirmarEmpresaEmitenteNfe')
            ->assertRedirect(
                NfeResource::getUrl('index')
                .'?venda_id=99&empresa_emitente_id='.(int) $acessivel->id
            );

        $this->assertSame($antes, Nfe::query()->count());
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
    }

    public function test_confirmar_lote_inicia_fila_com_emitente(): void
    {
        [$matriz, $acessivel, , $user] = $this->seedCenarioCompleto();
        [, $order1, $venda1] = $this->seedPedidoFaturadoApto($matriz, $user);
        [, $order2, $venda2] = $this->seedPedidoFaturadoApto($matriz, $user);

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('abrirModalEmpresaEmitenteNfe', [(int) $venda1->id, (int) $venda2->id], 'lote');
        $component->set('nfeEmpresaEmitenteId', (int) $acessivel->id);
        $component
            ->call('confirmarEmpresaEmitenteNfe')
            ->assertSet('nfeEmitenteModalOpen', false)
            ->assertSet('nfeLoteProgressOpen', true)
            ->assertSet('nfeLoteEmpresaEmitenteId', (int) $acessivel->id)
            ->assertSet('nfeLoteFilaVendaIds', [(int) $venda1->id, (int) $venda2->id])
            ->assertNoRedirect();

        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        unset($order1, $order2);
    }

    private function livewireMonitor(User $user, Empresa $matriz)
    {
        ErpContext::clearMemo();
        session(['erp_empresa_id' => (int) $matriz->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $request = Request::create('http://127.0.0.1:8000/admin/forca-vendas-monitor', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8000',
        ]);
        $this->app->instance('request', $request);

        return Livewire::actingAs($user)->test(ListForcaVendasMonitor::class);
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedUsuarioComAcessoMonitor(bool $flag): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ MODAL',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => $flag,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $matriz->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $matriz->id]);
        $this->grantMonitorNfePermissions($user);

        return [$matriz, $user];
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: Empresa, 3: User}
     */
    private function seedCenarioCompleto(): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ',
            'ativo' => true,
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
        ]);
        $acessivel = Empresa::query()->create([
            'nome' => 'EMITENTE OK',
            'razao_social' => 'EMITENTE OK LTDA',
            'fantasia' => 'OK',
            'ativo' => true,
            'cnpj' => '11244477700049',
        ]);
        $semAcesso = Empresa::query()->create([
            'nome' => 'EMITENTE BLOQ',
            'razao_social' => 'EMITENTE BLOQ LTDA',
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
        $this->grantMonitorNfePermissions($user);

        return [$matriz, $acessivel, $semAcesso, $user];
    }

    /**
     * @return array{0: Empresa, 1: ForcaVendasOrder, 2: Venda}
     */
    private function seedPedidoFaturadoApto(Empresa $matriz, User $user): array
    {
        $cliente = \App\Models\Person::query()->create([
            'codigo' => 'C-NFE-'.random_int(1000, 9999),
            'pessoa_tipo' => \App\Models\Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente NFe Modal',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'empresa_id' => $matriz->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(10000, 99999),
            'data' => now()->toDateString(),
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $order = ForcaVendasOrder::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'empresa_id' => $matriz->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'venda_id' => $venda->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [],
        ]);

        return [$matriz, $order, $venda];
    }

    private function grantMonitorNfePermissions(User $user): void
    {
        foreach (['vendas.access', 'forca_vendas.access', 'nfe.access', 'nfe.emit'] as $key) {
            $user->userPermissions()->create(['permission_key' => $key]);
        }

        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        ErpContext::clearMemo();
        parent::tearDown();
    }
}
