<?php

namespace Tests\Feature;

use App\Filament\Pages\DreGerencialPage;
use App\Models\Empresa;
use App\Models\PlanoConta;
use App\Models\User;
use App\Support\Erp\Dre\DreDespesasGerenciaisService;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\Dre\DrePlanoClassificacaoPadraoService;
use App\Support\Erp\Dre\DrePlanoClassificacaoSugestoes;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DrePlanoClassificacaoPadraoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
        $this->createSchema();
    }

    public function test_sugestoes_por_descricao_normalizada(): void
    {
        $this->assertSame(
            DrePlanoClassificacaoCatalog::PATRIMONIAL,
            DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'FORNECEDORES', 'codigo' => 99]),
        );
        $this->assertSame(
            DrePlanoClassificacaoCatalog::RECEITA,
            DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'Vendas de mercadoria']),
        );
        $this->assertSame(
            DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL,
            DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'ENERGIA ELÉTRICA']),
        );
        $this->assertSame(
            DrePlanoClassificacaoCatalog::DESPESA_FINANCEIRA,
            DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'TAXA DE CARTÃO']),
        );
        $this->assertSame(
            DrePlanoClassificacaoCatalog::PATRIMONIAL,
            DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'COMPRA DE MERCADORIA']),
        );
        $this->assertNull(DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'IMPOSTOS']));
        $this->assertNull(DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'BOLETO BANCÁRIO']));
        $this->assertNull(DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'RETIRADA DE CAIXA']));
        $this->assertNull(DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'DEVOLUÇÃO DE MERCADORIA']));
        $this->assertNull(DrePlanoClassificacaoSugestoes::sugerir(['descricao' => 'PLANO PERSONALIZADO XYZ']));
    }

    public function test_aplicar_preserva_existente_e_ignora_ambiguo(): void
    {
        $empresa = $this->empresa();
        $energia = PlanoConta::query()->create(['codigo' => 5, 'descricao' => 'ENERGIA', 'dc' => 'D', 'ativo' => true]);
        $forn = PlanoConta::query()->create(['codigo' => 1, 'descricao' => 'FORNECEDORES', 'dc' => 'D', 'ativo' => true]);
        $imp = PlanoConta::query()->create(['codigo' => 7, 'descricao' => 'IMPOSTOS', 'dc' => 'D', 'ativo' => true]);
        $custom = PlanoConta::query()->create(['codigo' => 99, 'descricao' => 'MEU PLANO X', 'dc' => 'D', 'ativo' => true]);

        app(DreDespesasGerenciaisService::class)->salvarClassificacao(
            (int) $empresa->id,
            (int) $energia->id,
            DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA, // classificação manual “errada” preservada
        );

        $previa = app(DrePlanoClassificacaoPadraoService::class)->prever((int) $empresa->id);
        $ids = array_column($previa, 'plano_conta_id');
        $this->assertContains((int) $forn->id, $ids);
        $this->assertNotContains((int) $energia->id, $ids);
        $this->assertNotContains((int) $imp->id, $ids);
        $this->assertNotContains((int) $custom->id, $ids);

        $res = app(DrePlanoClassificacaoPadraoService::class)->aplicar((int) $empresa->id);
        $this->assertSame(1, $res['aplicados']);

        $mapa = app(DreDespesasGerenciaisService::class)->mapaClassificacao((int) $empresa->id);
        $this->assertSame(DrePlanoClassificacaoCatalog::DESPESA_ADMINISTRATIVA, $mapa[(int) $energia->id]);
        $this->assertSame(DrePlanoClassificacaoCatalog::PATRIMONIAL, $mapa[(int) $forn->id]);
        $this->assertArrayNotHasKey((int) $imp->id, $mapa);

        // Reaplicar não duplica / não altera.
        $res2 = app(DrePlanoClassificacaoPadraoService::class)->aplicar((int) $empresa->id);
        $this->assertSame(0, $res2['aplicados']);
    }

    public function test_nao_classificado_explicito_e_preservado(): void
    {
        $empresa = $this->empresa();
        $forn = PlanoConta::query()->create(['codigo' => 1, 'descricao' => 'FORNECEDORES', 'dc' => 'D', 'ativo' => true]);
        $agua = PlanoConta::query()->create(['codigo' => 8, 'descricao' => 'ÁGUA', 'dc' => 'D', 'ativo' => true]);

        app(DreDespesasGerenciaisService::class)->salvarClassificacao(
            (int) $empresa->id,
            (int) $forn->id,
            DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO,
        );
        app(DreDespesasGerenciaisService::class)->salvarClassificacao(
            (int) $empresa->id,
            (int) $agua->id,
            DrePlanoClassificacaoCatalog::PATRIMONIAL,
        );

        $detalhe = app(DrePlanoClassificacaoPadraoService::class)->preverDetalhado((int) $empresa->id);
        $ids = array_column($detalhe['itens'], 'plano_conta_id');
        $this->assertNotContains((int) $forn->id, $ids);
        $this->assertNotContains((int) $agua->id, $ids);
        $this->assertSame(2, $detalhe['resumo']['preservados']);

        app(DrePlanoClassificacaoPadraoService::class)->aplicar((int) $empresa->id);
        $mapa = app(DreDespesasGerenciaisService::class)->mapaClassificacao((int) $empresa->id);
        $this->assertSame(DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO, $mapa[(int) $forn->id]);
        $this->assertSame(DrePlanoClassificacaoCatalog::PATRIMONIAL, $mapa[(int) $agua->id]);
    }

    public function test_isolamento_empresa(): void
    {
        $e1 = $this->empresa();
        $e2 = $this->empresa();
        $forn = PlanoConta::query()->create(['codigo' => 1, 'descricao' => 'FORNECEDORES', 'dc' => 'D', 'ativo' => true]);

        app(DrePlanoClassificacaoPadraoService::class)->aplicar((int) $e1->id);
        $m1 = app(DreDespesasGerenciaisService::class)->mapaClassificacao((int) $e1->id);
        $m2 = app(DreDespesasGerenciaisService::class)->mapaClassificacao((int) $e2->id);
        $this->assertSame(DrePlanoClassificacaoCatalog::PATRIMONIAL, $m1[(int) $forn->id]);
        $this->assertArrayNotHasKey((int) $forn->id, $m2);
    }

    public function test_livewire_previa_e_confirmacao(): void
    {
        [$empresa] = $this->seedAdmin();
        PlanoConta::query()->create(['codigo' => 5, 'descricao' => 'ENERGIA', 'dc' => 'D', 'ativo' => true]);
        PlanoConta::query()->create(['codigo' => 7, 'descricao' => 'IMPOSTOS', 'dc' => 'D', 'ativo' => true]);

        $lw = Livewire::test(DreGerencialPage::class)
            ->call('abrirClassificar')
            ->assertSee('Aplicar padrão')
            ->call('abrirPreviaPadrao')
            ->assertSet('showPreviaPadrao', true)
            ->assertSee('prévia')
            ->assertSee('ENERGIA')
            ->assertSeeHtml('erp-dre-classificar--previa');

        $previa = $lw->get('previaPadrao');
        $this->assertCount(1, $previa);
        $this->assertSame('ENERGIA', $previa[0]['descricao']);
        $resumo = $lw->get('previaPadraoResumo');
        $this->assertSame(1, $resumo['aplicar']);

        $lw->call('confirmarAplicarPadrao')
            ->assertSet('showPreviaPadrao', false)
            ->assertSee('Despesa operacional');

        $mapa = app(DreDespesasGerenciaisService::class)->mapaClassificacao((int) $empresa->id);
        $this->assertContains(DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL, $mapa);

        // Segunda aplicação: prévia vazia com motivo visível (não silêncio).
        $lw->call('abrirPreviaPadrao')
            ->assertSet('showPreviaPadrao', true)
            ->assertSee('Nenhum plano elegível');
        $this->assertSame([], $lw->get('previaPadrao'));
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'razao_social' => 'EMP '.uniqid(),
            'nome_fantasia' => 'E',
            'ativo' => true,
        ]);
    }

    /** @return array{0: Empresa, 1: User} */
    private function seedAdmin(): array
    {
        $empresa = $this->empresa();
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);
        session(['erp_empresa_id' => $empresa->id]);
        ErpContext::clearMemo();
        $this->actingAs($admin);

        return [$empresa, $admin];
    }

    private function createSchema(): void
    {
        foreach (['dre_plano_classificacoes', 'planos_contas', 'users', 'empresas'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('descricao', 120)->nullable();
            $table->string('dc', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('dre_plano_classificacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('plano_conta_id');
            $table->string('classificacao', 40);
            $table->timestamps();
            $table->unique(['empresa_id', 'plano_conta_id']);
        });
    }
}
