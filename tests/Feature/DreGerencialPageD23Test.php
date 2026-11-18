<?php

namespace Tests\Feature;

use App\Filament\Pages\DreGerencialPage;
use App\Models\Empresa;
use App\Models\PlanoConta;
use App\Models\User;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DreGerencialPageD23Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
        $this->createMinimalSchema();
    }

    public function test_classificar_planos_e_gerar_visao_caixa(): void
    {
        [$empresa] = $this->seedAdmin();
        $plano = PlanoConta::query()->create([
            'codigo' => 5,
            'descricao' => 'ENERGIA',
            'dc' => 'D',
            'ativo' => true,
        ]);

        $cpId = DB::table('contas_pagar')->insertGetId([
            'empresa_id' => $empresa->id,
            'numero' => 'CP1',
            'emissao' => '2026-11-10',
            'vencimento' => '2026-11-20',
            'competencia' => '2026-11-10',
            'plano_conta_id' => $plano->id,
            'valor' => 60,
            'desconto' => 0,
            'juros' => 0,
            'valor_pago' => 60,
            'saldo' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('conta_pagar_pagamentos')->insert([
            'conta_pagar_id' => $cpId,
            'data' => '2026-11-12',
            'valor_parcela' => 60,
            'valor_pago' => 60,
            'plano_conta_id' => $plano->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $t0 = hrtime(true);
        Livewire::test(DreGerencialPage::class)
            ->call('abrirClassificar')
            ->assertSet('showClassificar', true)
            ->assertSee('Classificação DRE dos planos')
            ->assertSee('ENERGIA')
            ->call('salvarClassificacaoPlano', $plano->id, DrePlanoClassificacaoCatalog::DESPESA_OPERACIONAL)
            ->call('fecharClassificar')
            ->assertSet('showClassificar', false)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->call('gerar')
            ->assertSet('gerado', true)
            ->assertSee('Despesas operacionais')
            ->assertSee('60,00')
            ->assertSee('Classificar planos')
            ->assertSee('Parâmetros')
            ->assertSee('CMV');

        $ms = (hrtime(true) - $t0) / 1e6;
        $this->assertLessThan(2500, $ms, "D2.3 livewire {$ms}ms");
    }

    public function test_escape_fecha_modal_classificar(): void
    {
        $this->seedAdmin();
        PlanoConta::query()->create([
            'codigo' => 1,
            'descricao' => 'FORNECEDORES',
            'dc' => 'D',
            'ativo' => true,
        ]);

        Livewire::test(DreGerencialPage::class)
            ->call('abrirClassificar')
            ->assertSet('showClassificar', true)
            ->call('onEscape')
            ->assertSet('showClassificar', false);
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedAdmin(): array
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'EMP D23',
            'nome_fantasia' => 'D23',
        ]);
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

    private function createMinimalSchema(): void
    {
        foreach ([
            'dre_plano_classificacoes', 'conta_pagar_pagamentos', 'contas_pagar',
            'caixa_lancamentos', 'planos_contas', 'devolucoes_venda', 'ordens_servico',
            'pdv_vendas', 'pdv_caixa_sessoes', 'vendas', 'users', 'empresas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120)->nullable();
            $table->string('fantasia', 120)->nullable();
            $table->string('regime_tributario', 40)->nullable();
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
        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40);
            $table->date('data');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20);
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->decimal('valor_abertura', 15, 2)->default(0);
            $table->timestamp('aberto_em')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedInteger('numero')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('acrescimo', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('situacao', 4)->default('F');
            $table->timestamp('fechado_em')->nullable();
            $table->string('origem', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('ordens_servico', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->string('situacao', 20);
            $table->date('data_termino')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('total_servicos', 15, 2)->default(0);
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_geral', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('devolucoes_venda', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('situacao', 20);
            $table->date('data')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('planos_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->unique();
            $table->string('descricao', 120)->nullable();
            $table->string('dc', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('contas_pagar', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->date('emissao')->nullable();
            $table->date('vencimento')->nullable();
            $table->date('competencia')->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('conta_pagar_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conta_pagar_id');
            $table->date('data')->nullable();
            $table->decimal('valor_parcela', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->timestamps();
        });
        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->default(0);
            $table->date('emissao')->nullable();
            $table->string('documento', 40)->nullable();
            $table->string('historico', 255)->nullable();
            $table->unsignedBigInteger('plano_conta_id')->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
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
