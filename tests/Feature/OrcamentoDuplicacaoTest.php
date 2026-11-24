<?php

namespace Tests\Feature;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\CreateOrcamento;
use App\Filament\Resources\OrcamentoResource\Pages\ListOrcamentos;
use App\Models\Empresa;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Duplicar Orçamento — MySQL DEV com DatabaseTransactions (rollback).
 * SQLite :memory: está bloqueado por migration legada alheia a este escopo.
 *
 * Rodar: tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=OrcamentoDuplicacaoTest
 */
class OrcamentoDuplicacaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped(
                'OrcamentoDuplicacaoTest exige MySQL DEV (-c phpunit.dev-mysql.xml). Driver: '.DB::getDriverName()
            );
        }
    }

    public function test_listagem_redireciona_para_create_com_parametro_duplicar(): void
    {
        [$user, $origem] = $this->seedCenario();
        $countBefore = Orcamento::query()->count();

        Livewire::actingAs($user)
            ->test(ListOrcamentos::class)
            ->set('highlightedRecordId', (int) $origem->id)
            ->call('duplicarOrcamento')
            ->assertRedirect(OrcamentoResource::getUrl('create').'?duplicar='.(int) $origem->id);

        $this->assertSame($countBefore, Orcamento::query()->count());
        $this->assertSame(Orcamento::STATUS_FECHADO, $origem->fresh()->status);
    }

    public function test_create_preenche_itens_sem_cliente_nem_pagamento_e_nao_grava(): void
    {
        [$user, $origem] = $this->seedCenario();
        $origemNumero = (string) $origem->numero;
        $countBefore = Orcamento::query()->count();

        $component = Livewire::actingAs($user)
            ->withQueryParams(['duplicar' => (int) $origem->id])
            ->test(CreateOrcamento::class);

        $this->assertNull($component->get('clienteId'));
        $this->assertSame('', trim((string) $component->get('clienteSearch')));
        $this->assertSame('', trim((string) $component->get('formaPagamento')));
        $this->assertSame(Orcamento::STATUS_ABERTO, $component->get('data.status'));
        $this->assertSame(now()->format('Y-m-d'), $component->get('data.data'));
        $this->assertNotSame($origemNumero, (string) $component->get('data.numero'));

        $itens = $component->get('itens');
        $this->assertCount(2, $itens);

        $produto = collect($itens)->first(fn (array $row): bool => ! ($row['is_separador'] ?? false));
        $this->assertNotNull($produto);
        $this->assertNull($produto['id']);
        $this->assertSame('10,00', $produto['desconto']);
        $this->assertSame('5,00', $produto['acrescimo']);
        $this->assertStringContainsString('PECA COM OBS', (string) $produto['descricao']);

        $this->assertSame($countBefore, Orcamento::query()->count());
        $this->assertSame($origemNumero, (string) $origem->fresh()->numero);
        $this->assertSame(Orcamento::STATUS_FECHADO, $origem->fresh()->status);
    }

    public function test_bloqueia_duplicar_sem_selecao(): void
    {
        [$user] = $this->seedCenario();
        $countBefore = Orcamento::query()->count();

        Livewire::actingAs($user)
            ->test(ListOrcamentos::class)
            ->call('duplicarOrcamento')
            ->assertNoRedirect();

        $this->assertSame($countBefore, Orcamento::query()->count());
    }

    /**
     * @return array{0: User, 1: Orcamento}
     */
    private function seedCenario(): array
    {
        $suffix = (string) random_int(700000, 799999);

        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMPRESA ORC DUP '.$suffix,
            'ativo' => true,
        ]);

        $vendedor = Vendedor::query()->create([
            'codigo' => 'VD'.$suffix,
            'nome' => 'VEND DUP '.$suffix,
            'ativo' => true,
            'efetua_venda' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'vendedor_id' => $vendedor->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'DUP'.$suffix,
            'pessoa_tipo' => 'fisica',
            'nome_razao' => 'CLIENTE ORIGEM DUP '.$suffix,
            'ativo' => true,
            'is_cliente' => true,
        ]);

        $produto = Product::query()->create([
            'codigo' => 'DUP-P'.$suffix,
            'descricao' => 'PECA COM OBS '.$suffix,
            'preco_venda' => 100,
            'ativo' => true,
            'unidade' => 'UN',
        ]);

        $origem = Orcamento::query()->create([
            'numero' => '9'.substr($suffix, -5),
            'data' => now()->subDays(3)->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'subtotal' => 95,
            'desconto_valor' => 5,
            'percentual_desconto' => 5.26,
            'forma_pagamento' => 'CARTAO',
            'observacoes' => 'OBS CABECALHO NAO COPIAR',
            'total' => 90,
            'status' => Orcamento::STATUS_FECHADO,
            'plataforma' => Orcamento::PLATAFORMA_ERP,
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $origem->id,
            'item' => 1,
            'is_separador' => true,
            'product_id' => null,
            'quantidade' => 0,
            'preco_unitario' => 0,
            'total' => 0,
            'desconto' => 0,
            'descricao' => 'GRUPO SUSPENSAO',
        ]);

        // bruto 100 - desconto 10 + acrescimo 5 = total 95
        OrcamentoItem::query()->create([
            'orcamento_id' => $origem->id,
            'item' => 2,
            'is_separador' => false,
            'product_id' => $produto->id,
            'quantidade' => 1,
            'preco_unitario' => 100,
            'total' => 95,
            'desconto' => 10,
            'descricao' => 'PECA COM OBS '.$suffix,
        ]);

        return [$user, $origem];
    }
}
