<?php

namespace Tests\Feature;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Filament\Resources\NfeResource;
use App\Models\Empresa;
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
use App\Support\Fiscal\NfeEmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

/**
 * Fatia 4B2: resumo do lote com atalho Abrir NF-e (nfe_id após rascunho + falha).
 */
class NfeLoteResumoAbrirTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_falha_apos_rascunho_resultado_contem_nfe_id_e_resumo_mostra_abrir(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitFail('Certificado da emitente ausente.');

        $antes = Nfe::query()->count();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $resultados = $component->get('nfeLoteResultados');
        $this->assertCount(2, $resultados);
        $this->assertFalse($resultados[0]['ok']);
        $this->assertFalse($resultados[1]['ok']);
        $this->assertNotNull($resultados[0]['nfe_id']);
        $this->assertNotNull($resultados[1]['nfe_id']);
        $this->assertNotSame($resultados[0]['nfe_id'], $resultados[1]['nfe_id']);

        $this->assertSame($antes + 2, Nfe::query()->count());
        foreach ([$resultados[0]['nfe_id'], $resultados[1]['nfe_id']] as $nfeId) {
            $nfe = Nfe::query()->find($nfeId);
            $this->assertNotNull($nfe);
            $this->assertSame(Nfe::STATUS_ABERTA, $nfe->status);
            $this->assertSame((int) $emitente->id, (int) $nfe->empresa_id);
        }

        $component
            ->assertSet('nfeLoteResumoOpen', true)
            ->assertSee('Abrir NF-e')
            ->assertSeeHtml('nfe_id='.(int) $resultados[0]['nfe_id'])
            ->assertSeeHtml('nfe_id='.(int) $resultados[1]['nfe_id']);

        $this->assertSame(
            (int) $resultados[0]['nfe_id'],
            $component->instance()->nfeIdAbertaDoResumoLote((int) $resultados[0]['nfe_id']),
        );
    }

    public function test_acao_abrir_aponta_para_nfe_existente_sem_criar_outra(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitFail('Falha SEFAZ teste.');

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $nfeId = (int) $component->get('nfeLoteResultados')[0]['nfe_id'];
        $count = Nfe::query()->count();

        $url = NfeResource::getUrl('index').'?nfe_id='.$nfeId;
        $component->assertSeeHtml($url);
        $this->assertSame($count, Nfe::query()->count());
        $this->assertSame(Nfe::STATUS_ABERTA, Nfe::query()->find($nfeId)?->status);
    }

    public function test_falha_antes_do_rascunho_sem_botao_abrir(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        // Bloqueia criação (já possui NF-e ativa) — falha antes do rascunho novo.
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
        $this->assertNull($resultados[0]['nfe_id']);
        $this->assertTrue($resultados[1]['ok']);
        $this->assertNotNull($resultados[1]['nfe_id']);

        // Erro da venda A: sem Abrir (nfe_id null). Sucesso da B: não entra na lista de erros.
        $component
            ->assertSet('nfeLoteResumoOpen', true)
            ->assertSee('já possui NF-e')
            ->assertDontSee('Abrir NF-e');
        $this->assertNull($component->instance()->nfeIdAbertaDoResumoLote($resultados[0]['nfe_id'] ?? null));
    }

    public function test_sucesso_autorizada_nao_mostra_botao_retry(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitOk();

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $resultados = $component->get('nfeLoteResultados');
        $this->assertTrue($resultados[0]['ok']);
        $this->assertTrue($resultados[1]['ok']);

        $component
            ->assertSet('nfeLoteResumoOpen', true)
            ->assertSee('2 autorizada(s), 0 com erro.')
            ->assertDontSee('Abrir NF-e');
    }

    public function test_multiplos_erros_cada_botao_aponta_para_sua_nfe(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: true);
        $this->prepareFiscalParams($emitente, serie: 7, numero: 50);
        $this->mockSefazTransmitFail('Erro empresa #7.');

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id], (int) $emitente->id);
        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $r = $component->get('nfeLoteResultados');
        $idA = (int) $r[0]['nfe_id'];
        $idB = (int) $r[1]['nfe_id'];
        $this->assertGreaterThan(0, $idA);
        $this->assertGreaterThan(0, $idB);
        $this->assertNotSame($idA, $idB);
        $this->assertSame((int) $vendaA->id, (int) Nfe::query()->find($idA)?->venda_id);
        $this->assertSame((int) $vendaB->id, (int) Nfe::query()->find($idB)?->venda_id);

        $html = $component->html();
        $this->assertStringContainsString('nfe_id='.$idA, $html);
        $this->assertStringContainsString('nfe_id='.$idB, $html);
    }

    public function test_flag_desligado_continua_fluxo_antigo_com_nfe_id_em_falha(): void
    {
        [$matriz, $emitente, $user, $vendaA, $vendaB] = $this->seedCenario(flag: false);
        $this->prepareFiscalParams($matriz, serie: 1, numero: 10);
        $this->mockSefazTransmitFail('Falha matriz.');

        $component = $this->livewireMonitor($user, $matriz);
        $component->call('iniciarNfeLote', [(int) $vendaA->id, (int) $vendaB->id]);
        $this->assertNull($component->get('nfeLoteEmpresaEmitenteId'));

        $component->call('processarProximoItemNfeLote');
        $component->call('processarProximoItemNfeLote');

        $r = $component->get('nfeLoteResultados');
        $this->assertFalse($r[0]['ok']);
        $this->assertNotNull($r[0]['nfe_id']);
        $nfe = Nfe::query()->find($r[0]['nfe_id']);
        $this->assertSame((int) $matriz->id, (int) $nfe?->empresa_id);
        $component->assertSee('Abrir NF-e');
        unset($emitente);
    }

    public function test_single_permanece_intacta_redirect_venda_id(): void
    {
        [$matriz, $emitente, $user, $vendaA] = $this->seedCenario(flag: false);

        $this->mock(\App\Support\Erp\Nfe\NfeVendaMercadoriaService::class, function ($mock): void {
            $mock->shouldReceive('motivoInaptoParaNfe')->andReturn(null);
        });

        $order = $vendaA->forcaVendasOrder;
        $this->assertNotNull($order);

        $component = $this->livewireMonitor($user, $matriz);
        $component
            ->set('selecionados', [(string) (int) $order->id])
            ->call('emitirNfeDoBotao')
            ->assertRedirect(NfeResource::getUrl('index').'?venda_id='.$vendaA->id)
            ->assertSet('nfeLoteResumoOpen', false);

        $this->assertSame(0, Nfe::query()->count());
        unset($emitente);
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
                $nfe->update([
                    'status' => Nfe::STATUS_TRANSMITIDA,
                    'situacao' => Nfe::SITUACAO_TRANSMITIDA,
                    'chave' => str_repeat('8', 44),
                    'protocolo' => 'PROTO-OK',
                ]);

                return $nfe->fresh() ?? $nfe;
            }
        });
    }

    private function mockSefazTransmitFail(string $message): void
    {
        $this->app->instance(NfeEmissionService::class, new class($message)
        {
            public function __construct(private string $message) {}

            public function transmitir(Nfe $nfe, Empresa $empresa, ?callable $onProgress = null): Nfe
            {
                throw new \RuntimeException($this->message);
            }
        });
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: User, 3: Venda, 4: Venda}
     */
    private function seedCenario(bool $flag): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ 4B2',
            'razao_social' => 'MATRIZ 4B2 LTDA',
            'ativo' => true,
            'uf' => 'SC',
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => $flag,
        ]);
        $emitente = Empresa::query()->create([
            'nome' => 'FILIAL 4B2',
            'razao_social' => 'FILIAL 4B2 LTDA',
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
            'codigo' => 'C-4B2',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente 4B2',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'endereco' => 'Rua Teste',
            'numero' => '10',
            'bairro' => 'Centro',
            'cep' => '88010000',
            'uf' => 'SC',
            'cidade_nome' => 'Florianópolis',
            'cidade_codigo' => '4205407',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P-4B2',
            'descricao' => 'Produto 4B2',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $vendaA = $this->criarVenda($matriz, $user, $cliente, $produto, 41001);
        $vendaB = $this->criarVenda($matriz, $user, $cliente, $produto, 41002);

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

    private function criarVenda(
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
