<?php

namespace Tests\Feature;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Filament\Resources\NfeResource;
use App\Filament\Resources\NfeResource\Pages\ListNfes;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\OperacaoFiscal;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\VendasParametro;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Fiscal\NfeEmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

/**
 * Fatia 4B1: reabrir NF-e aberta existente via ?nfe_id= e ação Abrir NF-e no Monitor.
 */
class NfeAbrirExistenteTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_nfe_aberta_mesma_empresa_abre_modal(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $nfe = $this->criarNfeAberta($venda, (int) $matriz->id, serie: '1', numero: '10');

        $component = $this->livewireNfe($user, $matriz);
        $component->call('abrirNfeExistentePorId', (int) $nfe->id);

        $component
            ->assertSet('nfeModalOpen', true)
            ->assertSet('nfeModalRecordId', (int) $nfe->id)
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $matriz->id)
            ->assertSet('nfeForm.serie', '1')
            ->assertSet('nfeForm.numero', '10');

        $this->assertSame(1, Nfe::query()->count());
        unset($emitente);
    }

    public function test_nfe_aberta_filial_venda_matriz_com_acesso_abre_e_fix_pin(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $this->criarItemNfe($nfe, $venda);

        $component = $this->livewireNfe($user, $matriz);
        $component->call('abrirNfeExistentePorId', (int) $nfe->id);

        $component
            ->assertSet('nfeModalOpen', true)
            ->assertSet('nfeModalRecordId', (int) $nfe->id)
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id)
            ->assertSet('nfeModalVendaId', (int) $venda->id)
            ->assertSet('nfeForm.serie', '7')
            ->assertSet('nfeForm.numero', '50');

        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) ErpContext::currentEmpresaId());
        $this->assertSame(1, Nfe::query()->count());
    }

    public function test_f2_mantem_empresa_serie_numero_sem_consumir_novo(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $params = $this->prepareFiscalParams($emitente, serie: 7, numero: 51);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $this->criarItemNfe($nfe, $venda);

        $movBefore = EstoqueMovimentacao::query()->count();
        $crBefore = ContaReceber::query()->count();
        $countBefore = Nfe::query()->count();
        $numeroParamBefore = (int) $params->fresh()->numero_nfe;

        $component = $this->livewireNfe($user, $matriz);
        $component->call('abrirNfeExistentePorId', (int) $nfe->id);
        $component
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id)
            ->call('saveNfe')
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id)
            ->assertSet('nfeModalRecordId', (int) $nfe->id);

        $fresh = $nfe->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame((int) $emitente->id, (int) $fresh->empresa_id);
        $this->assertSame('7', (string) $fresh->serie);
        $this->assertSame('50', (string) $fresh->numero);
        $this->assertSame($countBefore, Nfe::query()->count());
        $this->assertSame($numeroParamBefore, (int) $params->fresh()->numero_nfe);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) $venda->fresh()->empresa_id);
        $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        $this->assertSame($crBefore, ContaReceber::query()->count());
    }

    public function test_f3_transmit_usa_empresa_da_nfe_filial(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $this->criarItemNfe($nfe, $venda);

        $capturedEmpresaId = null;
        $this->app->instance(NfeEmissionService::class, new class($capturedEmpresaId, $emitente) {
            public function __construct(
                private &$capturedEmpresaId,
                private Empresa $expectedEmitente,
            ) {}

            public function transmitir(Nfe $nfe, Empresa $empresa, ?callable $onProgress = null): Nfe
            {
                $this->capturedEmpresaId = (int) $empresa->id;

                if ((int) $empresa->id !== (int) $this->expectedEmitente->id) {
                    throw new \RuntimeException('Transmitiu com empresa da sessão em vez da emitente.');
                }

                $nfe->update([
                    'status' => Nfe::STATUS_TRANSMITIDA,
                    'situacao' => Nfe::SITUACAO_TRANSMITIDA,
                    'chave' => str_repeat('2', 44),
                    'protocolo' => 'RETRY-OK',
                ]);

                return $nfe->fresh() ?? $nfe;
            }
        });

        $component = $this->livewireNfe($user, $matriz);
        $component->call('abrirNfeExistentePorId', (int) $nfe->id);
        $component->call('transmitNfe');

        $this->assertSame((int) $emitente->id, $capturedEmpresaId);
        $this->assertSame(Nfe::STATUS_TRANSMITIDA, $nfe->fresh()->status);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
    }

    public function test_usuario_sem_acesso_a_filial_bloqueia(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $user->empresas()->sync([(int) $matriz->id]);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');

        $component = $this->livewireNfe($user, $matriz);
        $component
            ->call('abrirNfeExistentePorId', (int) $nfe->id)
            ->assertSet('nfeModalOpen', false)
            ->assertSet('nfeModalRecordId', null)
            ->assertNotified();

        $this->assertSame(1, Nfe::query()->count());
    }

    public function test_nfe_de_venda_de_outra_matriz_bloqueia(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $outraMatriz = Empresa::query()->create([
            'nome' => 'OUTRA MATRIZ',
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $vendaOutra = Venda::query()->create([
            'empresa_id' => $outraMatriz->id,
            'cliente_id' => $venda->cliente_id,
            'numero' => (string) random_int(40000, 49999),
            'data' => now()->toDateString(),
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);
        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'empresa_id' => $outraMatriz->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $venda->cliente_id,
            'venda_id' => $vendaOutra->id,
            'total' => 10,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
            'payload' => [],
        ]);

        $nfe = $this->criarNfeAberta($vendaOutra, (int) $emitente->id, serie: '1', numero: '1');

        $component = $this->livewireNfe($user, $matriz);
        $component
            ->call('abrirNfeExistentePorId', (int) $nfe->id)
            ->assertSet('nfeModalOpen', false)
            ->assertNotified();
    }

    public function test_filial_fora_do_pivot_emitente_bloqueia_cross_company(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $matriz->nfeEmitentes()->sync([]);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');

        $component = $this->livewireNfe($user, $matriz);
        $component
            ->call('abrirNfeExistentePorId', (int) $nfe->id)
            ->assertSet('nfeModalOpen', false)
            ->assertNotified();
    }

    public function test_transmitida_nao_abre_por_este_caminho(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $nfe->update([
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
        ]);

        $component = $this->livewireNfe($user, $matriz);
        $component
            ->call('abrirNfeExistentePorId', (int) $nfe->id)
            ->assertSet('nfeModalOpen', false)
            ->assertNotified();
    }

    public function test_listagem_fiscal_matriz_nao_mostra_nfe_da_filial(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $nfeFilial = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $nfeMatriz = $this->criarNfeAberta($venda, (int) $matriz->id, serie: '1', numero: '1');

        $component = $this->livewireNfe($user, $matriz);
        $method = new \ReflectionMethod(ListNfes::class, 'getTableQuery');
        $method->setAccessible(true);
        $ids = $method->invoke($component->instance())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $nfeMatriz->id, $ids);
        $this->assertNotContains((int) $nfeFilial->id, $ids);
    }

    public function test_monitor_abrir_nfe_redireciona_para_nfe_id_existente(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $order = $venda->forcaVendasOrder;
        $this->assertNotNull($order);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');

        $component = $this->livewireMonitor($user, $matriz);
        $component->set('selecionados', [(string) (int) $order->id]);
        $component->set('highlightedRecordId', (int) $order->id);
        unset($component->nfeAbrirEstado);

        $estado = $component->get('nfeAbrirEstado');
        $this->assertTrue($estado['enabled'] ?? false);
        $this->assertSame((int) $nfe->id, (int) ($estado['nfe_id'] ?? 0));

        $component
            ->call('abrirNfeAbertaDoBotao')
            ->assertRedirect(NfeResource::getUrl('index').'?nfe_id='.$nfe->id);

        $this->assertSame(1, Nfe::query()->count());
    }

    public function test_monitor_abrir_desabilitado_quando_nfe_transmitida(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenario();
        $order = $venda->forcaVendasOrder;
        $this->assertNotNull($order);

        $nfe = $this->criarNfeAberta($venda, (int) $emitente->id, serie: '7', numero: '50');
        $nfe->update([
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
        ]);

        $component = $this->livewireMonitor($user, $matriz);
        $component->set('selecionados', [(string) (int) $order->id]);
        $component->set('highlightedRecordId', (int) $order->id);
        unset($component->nfeAbrirEstado);

        $estado = $component->get('nfeAbrirEstado');
        $this->assertFalse($estado['enabled'] ?? true);
        $this->assertNull($estado['nfe_id']);
    }

    private function livewireNfe(User $user, Empresa $matriz)
    {
        ErpContext::clearMemo();
        session(['erp_empresa_id' => (int) $matriz->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $request = Request::create(
            'http://127.0.0.1:8000/admin/nfe',
            'GET',
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_HOST' => '127.0.0.1:8000',
            ],
        );
        $this->app->instance('request', $request);

        return Livewire::actingAs($user)->test(ListNfes::class);
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
     * @return array{0: Empresa, 1: Empresa, 2: User, 3: Venda}
     */
    private function seedCenario(): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ ABRIR',
            'razao_social' => 'MATRIZ ABRIR LTDA',
            'ativo' => true,
            'uf' => 'SC',
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
        ]);
        $emitente = Empresa::query()->create([
            'nome' => 'FILIAL ABRIR',
            'razao_social' => 'FILIAL ABRIR LTDA',
            'fantasia' => 'FILIAL',
            'ativo' => true,
            'uf' => 'SC',
            'cnpj' => '11244477700049',
        ]);
        $matriz->nfeEmitentes()->sync([(int) $emitente->id]);

        $user = User::factory()->create([
            'empresa_id' => $matriz->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $matriz->id, (int) $emitente->id]);
        foreach (['vendas.access', 'forca_vendas.access', 'nfe.access', 'nfe.emit'] as $key) {
            $user->userPermissions()->create(['permission_key' => $key]);
        }
        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());

        $cliente = Person::query()->create([
            'codigo' => 'C-ABRIR',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Abrir NFe',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'endereco' => 'Rua Teste',
            'numero' => '100',
            'bairro' => 'Centro',
            'cep' => '88010000',
            'uf' => 'SC',
            'cidade_nome' => 'Florianópolis',
            'cidade_codigo' => '4205407',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P-ABRIR',
            'descricao' => 'Produto Abrir',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'empresa_id' => $matriz->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(50000, 59999),
            'data' => now()->toDateString(),
            'total' => 10,
            'status' => Venda::STATUS_ABERTO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        VendaItem::query()->create([
            'venda_id' => $venda->id,
            'product_id' => $produto->id,
            'quantidade' => 1,
            'valor_item' => 10,
            'total' => 10,
        ]);

        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
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

        OperacaoFiscal::forEmpresa((int) $matriz->id);
        OperacaoFiscal::forEmpresa((int) $emitente->id);
        $this->prepareFiscalParams($matriz, serie: 1, numero: 1);

        return [$matriz, $emitente, $user, $venda->fresh(['itens.product', 'cliente', 'forcaVendasOrder'])];
    }

    private function criarNfeAberta(Venda $venda, int $empresaId, string $serie, string $numero): Nfe
    {
        return Nfe::query()->create([
            'empresa_id' => $empresaId,
            'numero' => $numero,
            'serie' => $serie,
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'cliente_id' => $venda->cliente_id,
            'venda_id' => $venda->id,
            'total' => 10,
            'subtotal' => 10,
            'total_itens' => 1,
            'status' => Nfe::STATUS_ABERTA,
            'situacao' => Nfe::SITUACAO_ABERTA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
            'consumidor_final' => '1',
            'forma_pgto' => 'a_vista',
            'tipo_frete' => '9',
        ]);
    }

    private function criarItemNfe(Nfe $nfe, Venda $venda): NfeItem
    {
        $produto = $venda->itens->first()?->product
            ?? Product::query()->find($venda->itens->first()?->product_id);

        return NfeItem::query()->create([
            'nfe_id' => $nfe->id,
            'item' => 1,
            'product_id' => $produto?->id,
            'descricao' => $produto?->descricao ?? 'Item',
            'quantidade' => 1,
            'valor_unitario' => 10,
            'total' => 10,
            'cfop' => 5102,
            'unidade' => 'UN',
            'ncm' => '84713012',
            'origem' => '0',
            'cst' => '00',
            'csosn' => '102',
        ]);
    }

    private function prepareFiscalParams(Empresa $empresa, int $serie, int $numero): VendasParametro
    {
        $params = VendasParametro::forEmpresa((int) $empresa->id);
        $params->forceFill([
            'serie_nfe' => $serie,
            'numero_nfe' => $numero,
            'ambiente' => VendasParametro::AMBIENTE_HOMOLOGACAO,
        ])->save();

        return $params->fresh();
    }

    protected function tearDown(): void
    {
        ErpContext::clearMemo();
        parent::tearDown();
    }
}
