<?php

namespace Tests\Feature;

use App\Filament\Resources\NfeResource\Pages\ListNfes;
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
use App\Support\Erp\Nfe\NfeVendaMercadoriaService;
use App\Support\Fiscal\NfeEmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeSingleEmitenteTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_sem_empresa_emitente_id_nfe_usa_matriz_da_sessao(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($matriz, serie: 1, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 10);
        $this->prepareFiscalParams($emitente, serie: 7, ambiente: VendasParametro::AMBIENTE_PRODUCAO, numero: 50);

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
        ]);

        $component
            ->assertSet('nfeModalOpen', true)
            ->assertSet('nfeModalEmpresaEmitenteId', null)
            ->call('saveNfeDraftFromMount');

        $nfe = Nfe::query()->where('venda_id', $venda->id)->first();
        $this->assertNotNull($nfe);
        $this->assertSame((int) $matriz->id, (int) $nfe->empresa_id);
        $this->assertSame('1', (string) $nfe->serie);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) $venda->fresh()->empresa_id);
    }

    public function test_com_emitente_valida_nfe_nasce_na_emitente_sem_alterar_sessao_nem_venda(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($matriz, serie: 1, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 10);
        $this->prepareFiscalParams($emitente, serie: 7, ambiente: VendasParametro::AMBIENTE_PRODUCAO, numero: 50);

        $movBefore = EstoqueMovimentacao::query()->count();
        $crBefore = ContaReceber::query()->count();

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $emitente->id,
        ]);

        $component
            ->assertSet('nfeModalOpen', true)
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id)
            ->assertSet('nfeModalHomologacao', false) // emitente em produção
            ->call('saveNfeDraftFromMount');

        $nfe = Nfe::query()->where('venda_id', $venda->id)->first();
        $this->assertNotNull($nfe);
        $this->assertSame((int) $emitente->id, (int) $nfe->empresa_id);
        $this->assertSame((int) $venda->id, (int) $nfe->venda_id);
        $this->assertSame('7', (string) $nfe->serie);
        $this->assertSame('50', (string) $nfe->numero);

        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame((int) $matriz->id, (int) ErpContext::currentEmpresaId());
        $this->assertSame((int) $matriz->id, (int) $venda->fresh()->empresa_id);

        $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        $this->assertSame($crBefore, ContaReceber::query()->count());
    }

    public function test_emitente_manipulada_na_url_e_rejeitada(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $semAcesso = Empresa::query()->create([
            'nome' => 'SEM ACESSO',
            'ativo' => true,
            'uf' => 'SC',
        ]);
        $matriz->nfeEmitentes()->syncWithoutDetaching([(int) $semAcesso->id]);

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $semAcesso->id,
        ]);

        $component
            ->assertSet('nfeModalOpen', false)
            ->assertNotified();

        $this->assertSame(0, Nfe::query()->where('venda_id', $venda->id)->count());
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        unset($emitente);
    }

    public function test_segundo_save_mantem_empresa_id_da_emitente(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($emitente, serie: 3, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 20);

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $emitente->id,
        ]);
        $component->call('saveNfeDraftFromMount');

        $nfe = Nfe::query()->where('venda_id', $venda->id)->firstOrFail();
        $this->assertSame((int) $emitente->id, (int) $nfe->empresa_id);

        $component
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id)
            ->call('saveNfe')
            ->assertSet('nfeModalEmpresaEmitenteId', (int) $emitente->id);

        $this->assertSame((int) $emitente->id, (int) $nfe->fresh()->empresa_id);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
    }

    public function test_transmit_usa_empresa_da_nfe_nao_da_sessao(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($emitente, serie: 2, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 8);

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $emitente->id,
        ]);
        $component->call('saveNfeDraftFromMount');

        $nfe = Nfe::query()->where('venda_id', $venda->id)->firstOrFail();

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
                    'chave' => str_repeat('1', 44),
                    'protocolo' => '123',
                ]);

                return $nfe->fresh() ?? $nfe;
            }
        });

        $movBefore = EstoqueMovimentacao::query()->count();
        $crBefore = ContaReceber::query()->count();

        $component->call('transmitNfe');

        $this->assertSame((int) $emitente->id, $capturedEmpresaId);
        $this->assertSame(Nfe::STATUS_TRANSMITIDA, $nfe->fresh()->status);
        $this->assertSame((int) $emitente->id, (int) $nfe->fresh()->empresa_id);
        $this->assertSame((int) $matriz->id, (int) session('erp_empresa_id'));
        $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        $this->assertSame($crBefore, ContaReceber::query()->count());
    }

    public function test_payload_fiscal_usa_uf_da_emitente_para_cfop(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $matriz->update(['uf' => 'SC']);
        $emitente->update(['uf' => 'PR']);
        $venda->cliente->update(['uf' => 'SC']);

        OperacaoFiscal::forEmpresa((int) $matriz->id)->update([
            'cfop_venda_mercadoria_estadual' => 5102,
            'cfop_venda_mercadoria_interestadual' => 6102,
        ]);
        OperacaoFiscal::forEmpresa((int) $emitente->id)->update([
            'cfop_venda_mercadoria_estadual' => 5102,
            'cfop_venda_mercadoria_interestadual' => 6108,
        ]);

        $payloadMatriz = app(NfeVendaMercadoriaService::class)->montarPayload($venda->fresh(['itens.product', 'cliente', 'forcaVendasOrder.pedido']));
        $payloadEmitente = app(NfeVendaMercadoriaService::class)->montarPayload(
            $venda->fresh(['itens.product', 'cliente', 'forcaVendasOrder.pedido']),
            $emitente->fresh(),
        );

        $this->assertStringStartsWith('5102', $payloadMatriz['natureza_operacao']);
        $this->assertStringStartsWith('6108', $payloadEmitente['natureza_operacao']);
        $this->assertSame(5102, (int) $payloadMatriz['rows'][0]['cfop']);
        $this->assertSame(6108, (int) $payloadEmitente['rows'][0]['cfop']);
        unset($user);
    }

    public function test_tem_nfe_ativa_bloqueia_segunda_nota(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($emitente, serie: 1, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 1);

        $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $emitente->id,
        ])->call('saveNfeDraftFromMount');

        $this->assertSame(1, Nfe::query()->where('venda_id', $venda->id)->count());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('já possui NF-e');

        app(NfeVendaMercadoriaService::class)->montarPayload($venda->fresh(), $emitente);
    }

    public function test_serie_ambiente_e_cert_params_sao_da_emitente(): void
    {
        [$matriz, $emitente, $user, $venda] = $this->seedCenarioCompleto();
        $this->prepareFiscalParams($matriz, serie: 1, ambiente: VendasParametro::AMBIENTE_HOMOLOGACAO, numero: 1);
        $paramsEmitente = $this->prepareFiscalParams(
            $emitente,
            serie: 9,
            ambiente: VendasParametro::AMBIENTE_PRODUCAO,
            numero: 77,
        );
        $paramsEmitente->forceFill([
            'caminho_certificado' => 'C:\\certs\\emitente.pfx',
            'senha_certificado' => 'segredo-emitente',
        ])->save();

        $component = $this->livewireNfe($user, $matriz, [
            'venda_id' => (int) $venda->id,
            'empresa_emitente_id' => (int) $emitente->id,
        ]);

        $component->assertSet('nfeModalHomologacao', false);
        $this->assertSame('9', (string) $component->get('nfeForm.serie'));

        $resolved = VendasParametro::forEmpresa((int) $component->get('nfeModalEmpresaEmitenteId'));
        $this->assertSame('C:\\certs\\emitente.pfx', (string) $resolved->caminho_certificado);
        $this->assertNotSame(
            (string) VendasParametro::forEmpresa((int) $matriz->id)->caminho_certificado,
            (string) $resolved->caminho_certificado,
        );
    }

    /**
     * @param  array{venda_id: int, empresa_emitente_id?: int|null}  $open
     */
    private function livewireNfe(User $user, Empresa $matriz, array $open)
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

        $component = Livewire::actingAs($user)->test(ListNfes::class);

        $emitenteId = isset($open['empresa_emitente_id']) ? (int) $open['empresa_emitente_id'] : null;
        $component->call(
            'abrirNfeDeVendaMercadoria',
            (int) $open['venda_id'],
            $emitenteId && $emitenteId > 0 ? $emitenteId : null,
        );

        return $component;
    }

    /**
     * @return array{0: Empresa, 1: Empresa, 2: User, 3: Venda}
     */
    private function seedCenarioCompleto(): array
    {
        $matriz = Empresa::query()->create([
            'nome' => 'MATRIZ NFE',
            'razao_social' => 'MATRIZ NFE LTDA',
            'ativo' => true,
            'uf' => 'SC',
            'param_monitor_vendas_escolher_empresa_emitente_nfe' => true,
        ]);
        $emitente = Empresa::query()->create([
            'nome' => 'FILIAL EMITENTE',
            'razao_social' => 'FILIAL EMITENTE LTDA',
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
        foreach (['vendas.access', 'nfe.access', 'nfe.emit'] as $key) {
            $user->userPermissions()->create(['permission_key' => $key]);
        }
        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());

        $cliente = Person::query()->create([
            'codigo' => 'C-NFE-E',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Fiscal Completo',
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
            'codigo' => 'P-NFE-1',
            'descricao' => 'Produto NFe',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $venda = Venda::query()->create([
            'empresa_id' => $matriz->id,
            'cliente_id' => $cliente->id,
            'numero' => (string) random_int(20000, 29999),
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

        return [$matriz, $emitente, $user, $venda->fresh(['itens.product', 'cliente', 'forcaVendasOrder'])];
    }

    private function prepareFiscalParams(
        Empresa $empresa,
        int $serie,
        int $ambiente,
        int $numero,
    ): VendasParametro {
        $params = VendasParametro::forEmpresa((int) $empresa->id);
        $params->forceFill([
            'serie_nfe' => $serie,
            'numero_nfe' => $numero,
            'ambiente' => $ambiente,
        ])->save();

        return $params->fresh();
    }

    protected function tearDown(): void
    {
        ErpContext::clearMemo();
        parent::tearDown();
    }
}
