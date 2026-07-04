<?php

namespace Tests\Feature;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\CreateOrcamento;
use App\Filament\Resources\OrcamentoResource\Pages\EditOrcamento;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\PersonResource\Pages\CreatePerson;
use App\Filament\Resources\TerminalResource\Pages\ListTerminais;
use App\Models\Empresa;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ErpFormActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsErpUser(): User
    {
        $empresa = Empresa::query()->create([
            'nome' => 'UNITECHNOLOGIA SISTEMAS',
            'ativo' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
        ]);

        session(['erp_empresa_id' => $empresa->id]);

        $this->actingAs($user);

        return $user;
    }

    protected function createCliente(): Person
    {
        return Person::query()->create([
            'codigo' => '000001',
            'pessoa_tipo' => 'fisica',
            'nome_razao' => 'CLIENTE TESTE',
            'ativo' => true,
            'is_cliente' => true,
        ]);
    }

    protected function createVendedor(): Person
    {
        return Person::query()->create([
            'codigo' => '000002',
            'pessoa_tipo' => 'fisica',
            'nome_razao' => 'VENDEDOR TESTE',
            'ativo' => true,
            'is_funcionario' => true,
        ]);
    }

    protected function createProduct(): Product
    {
        return Product::query()->create([
            'codigo' => '000001',
            'descricao' => 'PRODUTO TESTE',
            'preco_venda' => 10,
            'preco_custo' => 5,
            'estoque' => 1,
            'ativo' => true,
        ]);
    }

    protected function createOrcamentoAberto(): Orcamento
    {
        $cliente = $this->createCliente();
        $vendedor = $this->createVendedor();
        $product = $this->createProduct();

        $orcamento = Orcamento::query()->create([
            'numero' => '000001',
            'data' => now()->format('Y-m-d'),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'subtotal' => 10,
            'percentual_desconto' => 0,
            'desconto_valor' => 0,
            'total' => 10,
            'status' => Orcamento::STATUS_ABERTO,
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $orcamento->id,
            'item' => 1,
            'product_id' => $product->id,
            'quantidade' => 1,
            'preco_unitario' => 10,
            'total' => 10,
            'desconto' => 0,
            'descricao' => 'PRODUTO TESTE',
        ]);

        return $orcamento;
    }

    public function test_orcamento_cancel_form_redirects_to_index(): void
    {
        $this->actingAsErpUser();

        $orcamento = $this->createOrcamentoAberto();

        Livewire::test(EditOrcamento::class, ['record' => $orcamento->id])
            ->call('cancelForm')
            ->assertRedirect(OrcamentoResource::getUrl('index'));
    }

    public function test_orcamento_post_save_escape_exits_to_index(): void
    {
        $this->actingAsErpUser();

        $orcamento = $this->createOrcamentoAberto();

        Livewire::test(EditOrcamento::class, ['record' => $orcamento->id])
            ->set('postSavePromptOpen', true)
            ->call('handlePostSavePromptEscape')
            ->assertSet('postSavePromptOpen', false)
            ->assertRedirect(OrcamentoResource::getUrl('index'));
    }

    public function test_orcamento_gravar_opens_post_save_prompt_when_editing(): void
    {
        $this->actingAsErpUser();

        $orcamento = $this->createOrcamentoAberto();

        Livewire::test(EditOrcamento::class, ['record' => $orcamento->id])
            ->call('gravarOrcamento')
            ->assertSet('postSavePromptOpen', true);
    }

    public function test_orcamento_create_cancel_redirects_to_index(): void
    {
        $this->actingAsErpUser();

        $this->createVendedor();

        Livewire::test(CreateOrcamento::class)
            ->call('cancelForm')
            ->assertRedirect(OrcamentoResource::getUrl('index'));
    }

    public function test_person_create_cancel_redirects_to_index(): void
    {
        $this->actingAsErpUser();

        Livewire::test(CreatePerson::class)
            ->call('cancelForm')
            ->assertRedirect(PersonResource::getUrl('index', ['tipo' => 'clientes']));
    }

    public function test_terminais_close_screen_redirects_to_dashboard(): void
    {
        $this->actingAsErpUser();

        Livewire::test(ListTerminais::class)
            ->call('closeScreen')
            ->assertRedirect(filament()->getUrl());
    }
}
