<?php

namespace Tests\Feature;

use App\Filament\Resources\NfeResource\Pages\ListNfes;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\VendasParametro;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfeDuplicacaoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_duplicar_pela_listagem_abre_modal_da_nova_nfe_aberta(): void
    {
        [$empresa, $user, $origem, $params] = $this->seedCenario();

        $movBefore = Schema::hasTable('estoque_movimentacoes')
            ? EstoqueMovimentacao::query()->count()
            : 0;

        $component = Livewire::actingAs($user)
            ->test(ListNfes::class)
            ->set('highlightedRecordId', (int) $origem->id)
            ->call('duplicarNfe')
            ->assertSet('nfeDuplicarModalOpen', true)
            ->assertSet('nfeDuplicarOrigemId', (int) $origem->id)
            ->call('confirmDuplicarNfe');

        $nova = Nfe::query()->where('id', '!=', $origem->id)->orderByDesc('id')->first();
        $this->assertNotNull($nova);
        $this->assertSame(Nfe::STATUS_ABERTA, $nova->status);
        $this->assertSame('200', (string) $nova->numero);
        $this->assertNull($nova->chave);
        $this->assertNull($nova->venda_id);
        $this->assertSame(2, $nova->itens()->count());
        $this->assertSame(1, $nova->faturas()->count());
        $this->assertSame(0, $nova->referencias()->count());

        $component
            ->assertSet('nfeModalOpen', true)
            ->assertSet('nfeModalRecordId', (int) $nova->id)
            ->assertSet('nfeModalStatus', 'ABERTA')
            ->assertSet('nfeForm.numero', '200')
            ->assertSet('nfeDuplicarModalOpen', false);

        $origemFresh = $origem->fresh();
        $this->assertSame(Nfe::STATUS_TRANSMITIDA, $origemFresh->status);
        $this->assertSame('CHAVEFEATURE12345678901234567890123456789012', $origemFresh->chave);
        $this->assertSame(201, (int) $params->fresh()->numero_nfe);

        if (Schema::hasTable('estoque_movimentacoes')) {
            $this->assertSame($movBefore, EstoqueMovimentacao::query()->count());
        }

        unset($empresa);
    }

    public function test_bloqueia_duplicar_sem_selecao_e_situacao_invalida(): void
    {
        [$empresa, $user, $origem] = $this->seedCenario();
        $origem->update([
            'status' => Nfe::STATUS_INUTILIZADA,
            'situacao' => Nfe::SITUACAO_INUTILIZADA,
            'chave' => null,
            'protocolo' => null,
            'xml' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ListNfes::class)
            ->call('duplicarNfe')
            ->assertSet('nfeDuplicarModalOpen', false)
            ->set('highlightedRecordId', (int) $origem->id)
            ->call('duplicarNfe')
            ->assertSet('nfeDuplicarModalOpen', false);

        $this->assertSame(1, Nfe::query()->count());
        unset($empresa);
    }

    /**
     * @return array{0: Empresa, 1: User, 2: Nfe, 3: VendasParametro}
     */
    private function seedCenario(): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA FEATURE DUP',
            'razao_social' => 'EMPRESA FEATURE DUP LTDA',
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        $user->empresas()->sync([(int) $empresa->id]);
        foreach (['vendas.access', 'nfe.access', 'nfe.emit'] as $key) {
            $user->userPermissions()->create(['permission_key' => $key]);
        }
        ErpAccess::forgetSession();
        ErpAccess::storeInSession($user, $user->effectivePermissionKeys());

        session(['erp_empresa_id' => (int) $empresa->id]);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'C-FDUP',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'Cliente Feature Dup',
            'cpf_cnpj' => '52998224725',
            'is_cliente' => true,
            'ativo' => true,
            'uf' => 'SC',
        ]);

        $produto = Product::query()->create([
            'codigo' => 'P-FDUP',
            'descricao' => 'Produto Feature Dup',
            'preco_venda' => 25,
            'estoque' => 50,
            'ativo' => true,
        ]);

        $params = VendasParametro::forEmpresa((int) $empresa->id);
        $params->forceFill([
            'serie_nfe' => 1,
            'numero_nfe' => 200,
            'ambiente' => VendasParametro::AMBIENTE_HOMOLOGACAO,
        ])->save();

        $origem = Nfe::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '88',
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => '2025-06-01',
            'data_saida' => '2025-06-01',
            'cliente_id' => $cliente->id,
            'chave' => 'CHAVEFEATURE12345678901234567890123456789012',
            'protocolo' => 'PROT-FEAT',
            'xml' => '<nfe/>',
            'subtotal' => 50,
            'desconto' => 0,
            'frete' => 0,
            'total' => 50,
            'total_itens' => 2,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
            'consumidor_final' => '1',
            'forma_pgto' => 'a_vista',
            'meio_pgto' => 'dinheiro',
            'tipo_frete' => '9',
        ]);

        NfeItem::query()->create([
            'nfe_id' => $origem->id,
            'item' => 1,
            'product_id' => $produto->id,
            'descricao' => 'Item A',
            'quantidade' => 1,
            'valor_unitario' => 20,
            'total' => 20,
            'cfop' => 5102,
            'unidade' => 'UN',
            'ncm' => '84713012',
            'cst' => '00',
            'csosn' => '102',
            'origem' => 0,
        ]);
        NfeItem::query()->create([
            'nfe_id' => $origem->id,
            'item' => 2,
            'product_id' => $produto->id,
            'descricao' => 'Item B',
            'quantidade' => 2,
            'valor_unitario' => 15,
            'total' => 30,
            'cfop' => 5102,
            'unidade' => 'UN',
            'ncm' => '84713012',
            'cst' => '00',
            'csosn' => '102',
            'origem' => 0,
        ]);
        NfeFatura::query()->create([
            'nfe_id' => $origem->id,
            'empresa_id' => $empresa->id,
            'numero' => '001',
            'data_vencimento' => '2025-07-01',
            'valor' => 50,
        ]);

        return [$empresa, $user, $origem->fresh(['itens', 'faturas']) ?? $origem, $params->fresh()];
    }

    protected function tearDown(): void
    {
        ErpContext::clearMemo();
        parent::tearDown();
    }
}
