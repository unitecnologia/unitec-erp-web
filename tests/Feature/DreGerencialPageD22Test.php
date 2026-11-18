<?php

namespace Tests\Feature;

use App\Filament\Pages\DreGerencialPage;
use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DreGerencialPageD22Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
        $this->createMinimalSchema();
    }

    public function test_permissao_no_catalogo(): void
    {
        $this->assertContains('dre_gerencial.access', ErpPermissionCatalog::allKeys());
        $modules = ErpPermissionCatalog::modules();
        $this->assertArrayHasKey('dre_gerencial', $modules);
        $this->assertSame('Financeiro', $modules['dre_gerencial']['group']);
    }

    public function test_abertura_sem_calculo_e_periodo_mes_corrente(): void
    {
        [$empresa, $admin] = $this->seedAdmin();
        $this->venda($empresa->id, '999.00', now()->toDateString());

        DB::flushQueryLog();
        DB::enableQueryLog();

        $lw = Livewire::test(DreGerencialPage::class)
            ->assertOk()
            ->assertSet('gerado', false)
            ->assertSee('Apuração parcial')
            ->assertSee('Informe o período e clique em Gerar')
            ->assertSee('Receita comercial')
            ->assertSee('Resultado bruto')
            ->assertSee('Fechar');

        $hoje = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $lw->assertSet('periodoDe', $inicioMes)
            ->assertSet('periodoAte', $hoje);

        $sql = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        $this->assertStringNotContainsString('sum(`total`)', strtolower($sql));
        $this->assertStringNotContainsString('sum(total)', strtolower($sql));
        DB::disableQueryLog();
    }

    public function test_gerar_receitas_e_ausencias(): void
    {
        [$empresa, $admin] = $this->seedAdmin();
        $this->venda($empresa->id, '100.00', '2026-11-10');
        $this->os($empresa->id, '2026-11-12', 50.00, 20.00);
        DB::table('devolucoes_venda')->insert([
            'empresa_id' => $empresa->id,
            'numero' => 'D1',
            'situacao' => 'finalizada',
            'tipo_devolucao' => 'parcial',
            'data' => '2026-11-15',
            'total' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mem0 = memory_get_usage(true);
        $t0 = hrtime(true);

        Livewire::test(DreGerencialPage::class)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->call('gerar')
            ->assertSet('gerado', true)
            ->assertSee('170,00')
            ->assertSee('10,00')
            ->assertSee('160,00')
            ->assertSee('Receita após devoluções')
            ->assertSee('CMV')
            ->assertSee('Despesas operacionais')
            ->assertSee('Parâmetros')
            ->assertSee('Pendências')
            ->assertDontSee('Tributos (informativo')
            ->assertSeeHtml('is-right">—</td>');

        $ms = (hrtime(true) - $t0) / 1e6;
        $this->assertLessThan(2000, $ms, "gerar {$ms}ms");
        $this->assertLessThan(24 * 1024 * 1024, memory_get_usage(true) - $mem0);
    }

    public function test_desconto_informativo_e_empresa_isolada(): void
    {
        [$e1, $admin] = $this->seedAdmin();
        $e2 = Empresa::query()->create([
            'razao_social' => 'OUTRA',
            'nome_fantasia' => 'O',
        ]);

        $this->venda($e1->id, '80.00', '2026-11-11', 'pdv');
        $sessao = DB::table('pdv_caixa_sessoes')->insertGetId([
            'user_id' => $admin->id,
            'empresa_id' => $e1->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vendaId = (int) DB::table('vendas')->where('empresa_id', $e1->id)->value('id');
        DB::table('pdv_vendas')->insert([
            'pdv_caixa_sessao_id' => $sessao,
            'venda_id' => $vendaId,
            'numero' => 1,
            'subtotal' => 90,
            'desconto' => 10,
            'acrescimo' => 0,
            'total' => 80,
            'situacao' => 'F',
            'fechado_em' => '2026-11-11 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->venda($e2->id, '500.00', '2026-11-11');

        Livewire::test(DreGerencialPage::class)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->call('gerar')
            ->assertSee('80,00')
            ->assertDontSee('500,00');
    }

    public function test_sem_vendas_mostra_zeros_apurados_e_pendencias(): void
    {
        $this->seedAdmin();

        Livewire::test(DreGerencialPage::class)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-30')
            ->call('gerar')
            ->assertSee('0,00')
            ->assertSee('CMV')
            ->assertSee('Pendências');
    }

    public function test_periodo_invalido(): void
    {
        $this->seedAdmin();

        Livewire::test(DreGerencialPage::class)
            ->set('periodoDe', '2026-12-01')
            ->set('periodoAte', '2026-11-01')
            ->call('gerar')
            ->assertSet('gerado', false)
            ->assertSee('período válido');
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedAdmin(): array
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'EMP DRE',
            'nome_fantasia' => 'DRE',
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

    private function venda(int $empresaId, string $total, string $data, string $plataforma = 'erp'): void
    {
        Venda::query()->create([
            'empresa_id' => $empresaId,
            'numero' => 'N'.uniqid(),
            'data' => $data,
            'cliente_id' => 1,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $plataforma,
        ]);
    }

    private function os(int $empresaId, string $data, float $servicos, float $pecas): void
    {
        OrdemServico::query()->create([
            'empresa_id' => $empresaId,
            'numero' => 'OS'.uniqid(),
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'data_termino' => $data,
            'cliente_id' => 1,
            'total_servicos' => $servicos,
            'total_produtos' => $pecas,
            'total_geral' => $servicos + $pecas,
            'subtotal' => $servicos + $pecas,
            'subtotal_servicos' => $servicos,
            'subtotal_pecas' => $pecas,
            'vl_desc_pecas' => 0,
            'vl_desc_servicos' => 0,
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'devolucoes_venda', 'ordens_servico', 'pdv_vendas', 'pdv_caixa_sessoes',
            'vendas', 'users', 'empresas',
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
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('subtotal_pecas', 15, 2)->default(0);
            $table->decimal('subtotal_servicos', 15, 2)->default(0);
            $table->decimal('vl_desc_pecas', 15, 2)->default(0);
            $table->decimal('vl_desc_servicos', 15, 2)->default(0);
            $table->decimal('total_servicos', 15, 2)->default(0);
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_geral', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('devolucoes_venda', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->string('situacao', 20);
            $table->string('tipo_devolucao', 20)->nullable();
            $table->date('data')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
