<?php

namespace Tests\Feature;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\OperacaoFiscal;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\VendasParametro;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeVendaLoteEmissionService;
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use App\Support\Fiscal\NfeEmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeLoteEmitenteTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_flag_false_lote_usa_empresa_da_sessao(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: false);
        $this->prepareFiscalParams($matriz, serie: 1, numero: 10);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        $movBefore = EstoqueMovimentacao::query()->count();
        $crBefore = ContaReceber::query()->count();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id]);
        $component->assertSet('nfeLoteEmpresaEmitenteId', null);
        $component->assertSet('nfeLoteProgressOpen', true);

        $component->call('processarProximoItemNfeLote');
        $r1 = $component->get('nfeLoteResultados');
        $this->assertTrue($r1[0]['ok'] ?? false, 'item1: '.json_encode($r1[0] ?? null, JSON_UNESCAPED_UNICODE));
        $component->call('processarProximoItemNfeLote');
        $r2 = $component->get('nfeLoteResultados');
        $this->assertTrue($r2[1]['ok'] ?? false, 'item2: '.json_encode($r2[1] ?? null, JSON_UNESCAPED_UNICODE));

        $nfes = Nfe::query()->whereIn('venda_id', [$vendaA->id, $vendaB->id])->orderBy('id')->get();
        $this->assertCount(2, $nfes);
        foreach ($nfes as $nfe) {
            $this->assertSame((int) $matriz->id, (int) $nfe->empresa_id);
            $this->assertSame('1', (string) $nfe->serie);
        }

        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) $vendaA->fresh()->empresa_id);
        $this->assertSame((int) $matriz->id, (int) $vendaB->fresh()->empresa_id);
        $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        $this->assertSame($crBefore, ContaReceber::query()->count());
        $component->assertSet('nfeLoteEmpresaEmitenteId', null);
    }

    public function test_flag_true_lote_usa_mesma_emitente_em_ambas_as_nfes(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);
        $this->prepareFiscalParams($matriz, serie: 1, numero: 10);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        $movBefore = EstoqueMovimentacao::query()->count();
        $crBefore = ContaReceber::query()->count();
        $numMatrizBefore = (int) VendasParametro::forEmpresa((int) $matriz->id)->numero_nfe;
        $numEmitBefore = (int) VendasParametro::forEmpresa((int) $emitente->id)->numero_nfe;

        $component = $this->livewireMonitor($user, $matriz);
        $component
            ->call('abrirModalEmpresaEmitenteNfe', [(int) $vendaA->id, (int) $vendaB->id], 'lote')
            ->set('nfeEmpresaEmitenteId', (int) $emitente->id)
            ->call('confirmarEmpresaEmitenteNfe')
            ->assertSet('nfeLoteEmpresaEmitenteId', (int) $emitente->id)
            ->assertSet('nfeLoteProgressOpen', true);

        $component->call('processarProximoItemNfeLote');
        $component->assertSet('nfeLoteEmpresaEmitenteId', (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');

        $nfes = Nfe::query()->whereIn('venda_id', [$vendaA->id, $vendaB->id])->orderBy('id')->get();
        $this->assertCount(2, $nfes);
        foreach ($nfes as $nfe) {
            $this->assertSame((int) $emitente->id, (int) $nfe->empresa_id);
            $this->assertSame('7', (string) $nfe->serie);
            $this->assertContains((int) $nfe->venda_id, [(int) $vendaA->id, (int) $vendaB->id]);
        }
        $this->assertSame(['50', '51'], $nfes->pluck('numero')->map(fn ($n) => (string) $n)->all());

        $this->assertSame($numMatrizBefore, (int) VendasParametro::forEmpresa((int) $matriz->id)->numero_nfe);
        $this->assertSame($numEmitBefore + 2, (int) VendasParametro::forEmpresa((int) $emitente->id)->numero_nfe);

        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) ErpContext::currentEmpresaId());
        $this->assertSame((int) $matriz->id, (int) $vendaA->fresh()->empresa_id);
        $this->assertSame((int) $matriz->id, (int) $vendaB->fresh()->empresa_id);
        $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        $this->assertSame($crBefore, ContaReceber::query()->count());
        $component->assertSet('nfeLoteEmpresaEmitenteId', null);
        $component->assertSet('nfeLoteResumoOpen', true);
    }

    public function test_emitente_invalida_no_inicio_nao_abre_lote(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);
        $semAcesso = Empresa::query()->create(['nome' => 'SEM ACESSO LOTE', 'ativo' => true]);
        $matriz->nfeEmitentes()->syncWithoutDetaching([(int) $semAcesso->id]);

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $semAcesso->id);

        $component
            ->assertSet('nfeLoteProgressOpen', false)
            ->assertSet('nfeLoteEmpresaEmitenteId', null)
            ->assertNotified();

        $this->assertSame(0, Nfe::query()->count());
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        unset($emitente, $user);
    }

    public function test_emitente_invalida_no_item_registra_falha_sem_fallback(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->assertSet('nfeLoteEmpresaEmitenteId', (int) $emitente->id);

        // Remove acesso no meio do lote (simula emitente deixando de ser válida).
        $user->empresas()->sync([(int) $matriz->id]);
        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user->fresh(), $user->fresh()->effectivePermissionKeys());

        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $resultados = $component->get('nfeLoteResultados');
        $this->assertCount(2, $resultados);
        $this->assertFalse($resultados[0]['ok']);
        $this->assertFalse($resultados[1]['ok']);
        $this->assertStringContainsString('emitente inválida', mb_strtolower($resultados[0]['erro'] ?? ''));
        $this->assertSame(0, Nfe::query()->count());
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $component->assertSet('nfeLoteEmpresaEmitenteId', null);
    }

    public function test_venda_com_nfe_ativa_fica_com_erro_e_outra_segue(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        Nfe::query()->create([
            'empresa_id' => $emitente->id,
            'numero' => '1',
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'cliente_id' => $vendaA->cliente_id,
            'venda_id' => $vendaA->id,
            'total' => 10,
            'status' => Nfe::STATUS_ABERTA,
            'situacao' => Nfe::SITUACAO_ABERTA,
        ]);

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $resultados = $component->get('nfeLoteResultados');
        $this->assertFalse($resultados[0]['ok']);
        $this->assertStringContainsString('já possui NF-e', $resultados[0]['erro'] ?? '');
        $this->assertTrue($resultados[1]['ok']);

        $nfeB = Nfe::query()->where('venda_id', $vendaB->id)->first();
        $this->assertNotNull($nfeB);
        $this->assertSame((int) $emitente->id, (int) $nfeB->empresa_id);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
    }

    public function test_falha_de_um_item_nao_troca_emitente_do_seguinte(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);
        $this->prepareFiscalParams($emitente, serie: 3, numero: 20);

        $calls = [];
        $this->app->instance(NfeEmissionService::class, new class($calls, $emitente) {
            public function __construct(private array &$calls, private Empresa $emitente) {}

            public function transmitir(Nfe $nfe, Empresa $empresa, ?callable $onProgress = null): Nfe
            {
                $this->calls[] = (int) $empresa->id;

                if (count($this->calls) === 1) {
                    throw new \RuntimeException('Falha simulada no primeiro item');
                }

                $nfe->update([
                    'status' => Nfe::STATUS_TRANSMITIDA,
                    'situacao' => Nfe::SITUACAO_TRANSMITIDA,
                    'chave' => str_repeat('9', 44),
                    'protocolo' => '999',
                ]);

                return $nfe->fresh() ?? $nfe;
            }
        });

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);

        $component->call('processarProximoItemNfeLote');
        $component->assertSet('nfeLoteEmpresaEmitenteId', (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');

        $resultados = $component->get('nfeLoteResultados');
        $this->assertFalse($resultados[0]['ok']);
        $this->assertTrue($resultados[1]['ok']);
        $this->assertSame([(int) $emitente->id, (int) $emitente->id], $calls);

        $nfeB = Nfe::query()->where('venda_id', $vendaB->id)->first();
        $this->assertSame((int) $emitente->id, (int) $nfeB?->empresa_id);
        $component->assertSet('nfeLoteEmpresaEmitenteId', null);
    }

    public function test_criar_rascunho_null_preserva_comportamento_antigo(): void
    {
        [$matriz, $emitente, $user, $vendaA] = $this->seedCenarioLote(flag: true);
        $this->prepareFiscalParams($matriz, serie: 2, numero: 5);
        $this->prepareFiscalParams($emitente, serie: 9, numero: 90);

        session(['erp_empresa_id' => (int) $matriz->id]);
        ErpContext::clearMemo();

        $payload = app(NfeVendaMercadoriaService::class)->montarPayload(
            $vendaA->fresh(['itens.product', 'cliente', 'forcaVendasOrder.pedido'])
        );
        $nfe = app(NfeVendaLoteEmissionService::class)->criarRascunho($vendaA, $payload, null);

        $this->assertSame((int) $matriz->id, (int) $nfe->empresa_id);
        $this->assertSame('2', (string) $nfe->serie);
        $this->assertSame('5', (string) $nfe->numero);
        unset($user, $emitente);
    }

    public function test_fechar_resumo_limpa_estado_emitente(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenarioLote(flag: true);

        $component = $this->livewireMonitor($user, $matriz);
        $component->set('nfeLoteEmpresaEmitenteId', (int) $emitente->id);
        $component->set('nfeLoteResumoOpen', true);
        $component->set('nfeLoteFilaVendaIds', [(int) $vendaA->id, (int) $vendaB->id]);
        $component->call('fecharNfeLoteResumo');

        $component
            ->assertSet('nfeLoteEmpresaEmitenteId', null)
            ->assertSet('nfeLoteFilaVendaIds', [])
            ->assertSet('nfeLoteResumoOpen', false);
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

    private function mockSefazTransmitOk(): void
    {
        $this->app->instance(NfeEmissionService::class, new class
        {
            public function transmitir(Nfe $nfe, Empresa $empresa, ?callable $onProgress = null): Nfe
            {
                if ($onProgress) {
                    $onProgress(1, 'Validando dados da NF-e');
                    $onProgress(4, 'Enviando à SEFAZ (aguardando resposta)');
                }

                $nfe->update([
                    'status' => Nfe::STATUS_TRANSMITIDA,
                    'situacao' => Nfe::SITUACAO_TRANSMITIDA,
                    'chave' => str_repeat('8', 44),
                    'protocolo' => 'PROTO-LOTE',
                ]);

                return $nfe->fresh() ?? $nfe;
            }
        });
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: User, 3: Venda, 4: Venda}
     */
    private function seedCenarioLote(bool $flag): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ LOTE',
            'razao_social' => 'MATRIZ LOTE LTDA',
            'ativo' => true,
            'uf' => 'SC',
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => $flag,
        ]);
        $emitente = Empresa::query()->create([
            'nome' => 'EMITENTE LOTE',
            'razao_social' => 'EMITENTE LOTE LTDA',
            'fantasia' => 'EMIT',
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
            'codigo' => 'C-LOTE',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Lote Completo',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'endereco' => 'Rua Lote',
            'numero' => '10',
            'bairro' => 'Centro',
            'cep' => '88010000',
            'uf' => 'SC',
            'cidade_nome' => 'Florianópolis',
            'cidade_codigo' => '4205407',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P-LOTE',
            'descricao' => 'Produto Lote',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $vendaA = $this->criarVendaFaturada($matriz, $user, $cliente, $produto, 30001);
        $vendaB = $this->criarVendaFaturada($matriz, $user, $cliente, $produto, 30002);

        OperacaoFiscal::forEmpresa((int) $matriz->id);
        OperacaoFiscal::forEmpresa((int) $emitente->id);

        return [
            $matriz,
            $emitente,
            $user,
            $vendaA->fresh(['itens.product', 'cliente', 'forcaVendasOrder']),
            $vendaB->fresh(['itens.product', 'cliente', 'forcaVendasOrder']),
        ];
    }

    private function criarVendaFaturada(
        Empresa $matriz,
        User $user,
        Person $cliente,
        Product $produto,
        int $numero,
    ): Venda {
        $venda = Venda::query()->create([
            'empresa_id' => $matriz->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) $numero,
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

        return $venda;
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
