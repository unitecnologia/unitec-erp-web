<?php

namespace Tests\Unit;

use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Reports\Tabular\Definitions\AjustesEstoqueReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): migrate :memory: completo
 * ainda falha em legado do projeto.
 */
class AjustesEstoqueReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
        ErpSchema::flush();
    }

    public function test_registry_e_colunas_sem_valorizacao(): void
    {
        $report = ReportRegistry::make('ajustes-estoque');
        $this->assertInstanceOf(AjustesEstoqueReport::class, $report);
        $this->assertSame('AJUSTES DE ESTOQUE', $report->title());
        $cols = $report->columns();
        $this->assertArrayHasKey('ajuste', $cols);
        $this->assertArrayHasKey('qtd_anterior', $cols);
        $this->assertArrayHasKey('tipo', $cols);
        $this->assertArrayHasKey('usuario', $cols);
        $this->assertArrayNotHasKey('operacao', $cols);
        $this->assertArrayNotHasKey('valor', $cols);
        $this->assertArrayNotHasKey('custo', $cols);
    }

    public function test_separa_ajustes_de_venda_e_nao_inventa_usuario_motivo(): void
    {
        $user = User::query()->create([
            'name' => 'Operador Ajuste',
            'email' => 'ajuste-report-'.uniqid('', true).'@test.local',
            'password' => 'secret',
        ]);

        $produto = Product::query()->create($this->produto([
            'codigo' => 'AJ-RPT-1',
            'descricao' => 'PRODUTO AJUSTE RELATORIO',
            'grupo' => 'PECAS',
            'unidade' => 'UN',
            'estoque' => 10,
        ]));

        $outro = Product::query()->create($this->produto([
            'codigo' => 'AJ-RPT-V',
            'descricao' => 'PRODUTO VENDA NAO ENTRA',
            'grupo' => 'PECAS',
            'unidade' => 'UN',
            'estoque' => 5,
        ]));

        // Ajuste #99 com modo gravado (somar) — não deduzir do delta.
        DB::table('ajustes_estoque')->insert([
            'id' => 99,
            'data' => '2026-11-20',
            'product_id' => $produto->id,
            'qtd_ajust' => 12,
            'modo' => 'somar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Ajuste #100 sem modo → Não identificado
        DB::table('ajustes_estoque')->insert([
            'id' => 100,
            'data' => '2026-11-21',
            'product_id' => $produto->id,
            'qtd_ajust' => -1,
            'modo' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EstoqueMovimentacao::query()->create([
            'empresa_id' => null,
            'produto_id' => $produto->id,
            'estoque_id' => null,
            'data_movimentacao' => '2026-11-20 10:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_AJUSTE,
            'quantidade' => 12,
            'saldo_anterior' => -2,
            'saldo_atual' => 10,
            'origem_tipo' => 'ajuste_estoque',
            'origem_id' => 99,
            'origem_numero' => '99',
            'usuario_id' => $user->id,
            'observacao' => 'Inventário físico',
        ]);

        EstoqueMovimentacao::query()->create([
            'empresa_id' => null,
            'produto_id' => $produto->id,
            'estoque_id' => null,
            'data_movimentacao' => '2026-11-21 08:15:00',
            'tipo' => EstoqueMovimentacao::TIPO_AJUSTE,
            'quantidade' => -1,
            'saldo_anterior' => 10,
            'saldo_atual' => 9,
            'origem_tipo' => 'ajuste_estoque',
            'origem_id' => 100,
            'origem_numero' => '100',
            'usuario_id' => null,
            'observacao' => null,
        ]);

        EstoqueMovimentacao::query()->create([
            'empresa_id' => null,
            'produto_id' => $produto->id,
            'estoque_id' => null,
            'data_movimentacao' => '2026-11-22 09:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
            'quantidade' => -12,
            'saldo_anterior' => 9,
            'saldo_atual' => -3,
            'origem_tipo' => 'ajuste_estoque',
            'origem_id' => 99,
            'origem_numero' => '99',
            'usuario_id' => $user->id,
            'observacao' => 'Exclusão do ajuste',
        ]);

        EstoqueMovimentacao::query()->create([
            'empresa_id' => null,
            'produto_id' => $outro->id,
            'estoque_id' => null,
            'data_movimentacao' => '2026-11-20 11:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_VENDA,
            'quantidade' => -2,
            'saldo_anterior' => 5,
            'saldo_atual' => 3,
            'origem_tipo' => 'venda',
            'origem_id' => 1,
            'origem_numero' => '1',
            'usuario_id' => $user->id,
            'observacao' => null,
        ]);
        EstoqueMovimentacao::query()->create([
            'empresa_id' => null,
            'produto_id' => $outro->id,
            'estoque_id' => null,
            'data_movimentacao' => '2026-11-20 12:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
            'quantidade' => 2,
            'saldo_anterior' => 3,
            'saldo_atual' => 5,
            'origem_tipo' => 'venda',
            'origem_id' => 1,
            'origem_numero' => '1',
            'usuario_id' => $user->id,
            'observacao' => null,
        ]);

        session(['erp_empresa_id' => null]);

        $built = $this->report()->build($this->request([
            'de' => '2026-11-01',
            'ate' => '2026-11-30',
            'tipo' => 'todos',
            'gerar' => '1',
        ]));

        $this->assertCount(3, $built['rows']);
        $this->assertSame('AJ-RPT-1', $built['rows'][0]['codigo']);
        $this->assertSame('-2', $built['rows'][0]['qtd_anterior']);
        $this->assertSame('+12', $built['rows'][0]['ajuste']);
        $this->assertSame('10', $built['rows'][0]['qtd_posterior']);
        $this->assertSame('Operador Ajuste', $built['rows'][0]['usuario']);
        $this->assertSame('Inventário físico', $built['rows'][0]['motivo']);
        $this->assertSame('AJUSTE MANUAL — SOMOU', $built['rows'][0]['tipo']);

        $this->assertSame('', $built['rows'][1]['usuario']);
        $this->assertSame('', $built['rows'][1]['motivo']);
        $this->assertSame('-1', $built['rows'][1]['ajuste']);
        // Sem modo gravado: não inventa SOMOU/SUBSTITUIU
        $this->assertSame('AJUSTE MANUAL', $built['rows'][1]['tipo']);

        $this->assertSame('ESTORNO DE AJUSTE', $built['rows'][2]['tipo']);
        $this->assertSame('-12', $built['rows'][2]['ajuste']);

        $this->assertSame('-1', $built['totals']['ajuste']);
        $this->assertTrue(collect($built['summary'])->contains(
            fn (string $line): bool => str_contains($line, 'TOTAL AJUSTE POR UNIDADE')
                && str_contains($line, 'UN')
        ));
        $this->assertTrue(collect($built['summary'])->contains(
            fn (string $line): bool => str_contains($line, 'VALORIZAÇÃO')
        ));
    }

    public function test_filtro_entrada_e_produto(): void
    {
        $produto = Product::query()->create($this->produto([
            'codigo' => 'AJ-ENT',
            'descricao' => 'ENTRADA FILTRO',
            'unidade' => 'UN',
        ]));

        DB::table('ajustes_estoque')->insert([
            'id' => 1,
            'data' => '2026-11-15',
            'product_id' => $produto->id,
            'qtd_ajust' => 5,
            'modo' => 'substituir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ajustes_estoque')->insert([
            'id' => 2,
            'data' => '2026-11-15',
            'product_id' => $produto->id,
            'qtd_ajust' => -2,
            'modo' => 'somar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EstoqueMovimentacao::query()->create([
            'produto_id' => $produto->id,
            'data_movimentacao' => '2026-11-15 10:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_AJUSTE,
            'quantidade' => 5,
            'saldo_anterior' => 0,
            'saldo_atual' => 5,
            'origem_tipo' => 'ajuste_estoque',
            'origem_id' => 1,
            'origem_numero' => '1',
        ]);
        EstoqueMovimentacao::query()->create([
            'produto_id' => $produto->id,
            'data_movimentacao' => '2026-11-15 11:00:00',
            'tipo' => EstoqueMovimentacao::TIPO_AJUSTE,
            'quantidade' => -2,
            'saldo_anterior' => 5,
            'saldo_atual' => 3,
            'origem_tipo' => 'ajuste_estoque',
            'origem_id' => 2,
            'origem_numero' => '2',
        ]);

        $built = $this->report()->build($this->request([
            'de' => '2026-11-01',
            'ate' => '2026-11-30',
            'tipo' => 'entrada',
            'q' => 'AJ-ENT',
            'gerar' => '1',
        ]));

        $this->assertCount(1, $built['rows']);
        $this->assertSame('+5', $built['rows'][0]['ajuste']);
        $this->assertSame('AJUSTE MANUAL — SUBSTITUIU', $built['rows'][0]['tipo']);
    }

    private function report(): AjustesEstoqueReport
    {
        return new AjustesEstoqueReport;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(array $query = []): Request
    {
        return Request::create('/admin/erp/reports/tabular/ajustes-estoque', 'GET', $query);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function produto(array $extra = []): array
    {
        return array_merge([
            'codigo' => 'P-'.uniqid(),
            'descricao' => 'PRODUTO TESTE',
            'unidade' => 'UN',
            'grupo' => '',
            'estoque' => 0,
            'preco_custo' => 0,
            'preco_venda' => 0,
            'ativo' => true,
            'is_servico' => false,
        ], $extra);
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('inventario_contagem_itens');
        Schema::dropIfExists('estoque_movimentacoes');
        Schema::dropIfExists('ajustes_estoque');
        Schema::dropIfExists('products');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
            $table->string('unidade')->nullable();
            $table->string('grupo')->nullable();
            $table->string('marca')->nullable();
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_compra', 15, 4)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('is_servico')->default(false);
            $table->string('tipo_produto')->nullable();
            $table->timestamps();
        });

        Schema::create('ajustes_estoque', function (Blueprint $table): void {
            $table->id();
            $table->date('data');
            $table->unsignedBigInteger('product_id');
            $table->decimal('qtd_ajust', 12, 3);
            $table->string('modo', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('estoque_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('estoque_id')->nullable();
            $table->dateTime('data_movimentacao');
            $table->string('tipo', 40);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('saldo_anterior', 12, 3);
            $table->decimal('saldo_atual', 12, 3);
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('origem_numero', 40)->nullable();
            $table->string('doc_fiscal_tipo', 20)->nullable();
            $table->string('doc_fiscal_numero', 40)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
