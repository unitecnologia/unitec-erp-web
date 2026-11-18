<?php

namespace Tests\Feature;

use App\Filament\Pages\DreGerencialPage;
use App\Models\Empresa;
use App\Models\PlanoConta;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\Dre\DreDemonstrativoGerencialService;
use App\Support\Erp\Dre\DreDespesasGerenciaisService;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\Dre\DreReceitasGerenciaisService;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DreDemonstrativoD25Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
        $this->createMinimalSchema();
    }

    public function test_deducoes_somente_devolucoes_sem_re_subtrair_desconto(): void
    {
        $rec = [
            'receita_bruta_operacional' => '100.00',
            'devolucoes' => '10.00',
            'receita_liquida_operacional' => '90.00',
            'descontos_embutidos_informativo' => '5.00',
        ];
        $des = [
            'custos_competencia' => '0.00',
            'despesas_operacionais' => '0.00',
            'despesas_administrativas' => '0.00',
            'despesas_comerciais' => '0.00',
            'despesas_financeiras_competencia' => '0.00',
        ];

        $d = app(DreDemonstrativoGerencialService::class)->montar($rec, $des);
        $this->assertSame('100.00', $d['receita_bruta']);
        $this->assertSame('10.00', $d['deducoes_receita']);
        $this->assertSame('90.00', $d['receita_liquida']);
        $this->assertSame('5.00', $d['descontos_embutidos_informativo']);
        $this->assertFalse($d['cmv_disponivel']);
        $this->assertNull($d['cmv']);
        $this->assertFalse($d['resultado_bruto_disponivel']);
        $this->assertFalse($d['resultado_operacional_disponivel']);
        $this->assertNull($d['resultado_bruto']);
        $this->assertNull($d['resultado_operacional']);
    }

    public function test_nao_usa_preco_custo_do_produto_como_cmv(): void
    {
        // products.preco_custo existe no schema real; no teste mínimo não criamos products.
        // Garantia: demonstrativo nunca preenche cmv.
        $d = app(DreDemonstrativoGerencialService::class)->montar([
            'receita_bruta_operacional' => '50.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => '50.00',
            'descontos_embutidos_informativo' => '0.00',
        ], ['custos_competencia' => '12.00']);

        $this->assertNull($d['cmv']);
        $this->assertFalse($d['cmv_disponivel']);
        $this->assertSame('12.00', $d['custos_cp_competencia']);
        $this->assertStringContainsString('não é cmv', mb_strtolower($d['custos_cp_observacao']));
        $this->assertStringContainsString('preco_custo', $d['limitacoes']['cadastro_produto']);
    }

    public function test_custos_cp_nao_viram_resultado_bruto(): void
    {
        $d = app(DreDemonstrativoGerencialService::class)->montar([
            'receita_bruta_operacional' => '200.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => '200.00',
            'descontos_embutidos_informativo' => '0.00',
        ], [
            'custos_competencia' => '80.00',
            'despesas_operacionais' => '10.00',
            'despesas_administrativas' => '0.00',
            'despesas_comerciais' => '0.00',
            'despesas_financeiras_competencia' => '0.00',
        ]);

        $this->assertSame('80.00', $d['custos_cp_competencia']);
        $this->assertFalse($d['resultado_bruto_disponivel']);
        $this->assertNull($d['resultado_bruto']);
        // Não inventa: líquida − custos CP.
        $this->assertNotSame('120.00', (string) ($d['resultado_bruto'] ?? ''));
    }

    public function test_livewire_estrutura_d25_e_regressao_receita(): void
    {
        [$empresa] = $this->seedAdmin();
        Venda::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => 'V25',
            'data' => '2026-11-10',
            'cliente_id' => 1,
            'total' => 100,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);

        $plano = PlanoConta::query()->create([
            'codigo' => 15,
            'descricao' => 'CUSTO CP',
            'dc' => 'D',
            'ativo' => true,
        ]);
        app(DreDespesasGerenciaisService::class)->salvarClassificacao(
            (int) $empresa->id,
            (int) $plano->id,
            DrePlanoClassificacaoCatalog::CUSTO
        );
        DB::table('contas_pagar')->insert([
            'empresa_id' => $empresa->id,
            'numero' => 'CP25',
            'emissao' => '2026-11-01',
            'vencimento' => '2026-11-20',
            'competencia' => '2026-11-01',
            'plano_conta_id' => $plano->id,
            'valor' => 40,
            'desconto' => 0,
            'juros' => 0,
            'multa' => 0,
            'valor_pago' => 0,
            'saldo' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $t0 = hrtime(true);
        Livewire::test(DreGerencialPage::class)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->call('gerar')
            ->assertSet('gerado', true)
            ->assertSee('Receita comercial')
            ->assertSee('Devoluções comprovadas')
            ->assertSee('Receita após devoluções')
            ->assertSee('CMV')
            ->assertSee('Custos CP')
            ->assertSee('Resultado bruto')
            ->assertSee('Resultado operacional gerencial')
            ->assertSee('Parâmetros')
            ->assertDontSee('Tributos (informativo')
            ->assertSee('100,00')
            ->assertSee('40,00')
            ->assertSet('resultado.demonstrativo.cmv_disponivel', false)
            ->assertSet('resultado.demonstrativo.resultado_bruto_disponivel', false)
            ->assertSet('resultado.demonstrativo.resultado_operacional_disponivel', false)
            ->assertSet('resultado.demonstrativo.tributos_exibir', false);

        $ms = (hrtime(true) - $t0) / 1e6;
        $this->assertLessThan(2500, $ms, "D2.5 gerar {$ms}ms");

        // Regressão D2.1 isolada.
        $rec = app(DreReceitasGerenciaisService::class)->calcular((int) $empresa->id, '2026-11-01', '2026-11-30');
        $this->assertSame('100.00', $rec['receita_liquida_operacional']);
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedAdmin(): array
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'EMP D25',
            'nome_fantasia' => 'D25',
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
