<?php

namespace Tests\Unit;

use App\Models\Venda;
use App\Support\Erp\Dre\VendaItemCustoHistorico;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadeProdutos\LucratividadeProdutosAggregator;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosLucratividadeReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMaisLucrativosReport;
use App\Support\Erp\Reports\Tabular\Definitions\ProdutosMenosLucrativosReport;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class LucratividadeProdutosAggregatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_usa_custo_historico_com_rateio_e_nao_cadastro_atual(): void
    {
        $p = $this->produto(10.0, codigo: 'A1');
        DB::table('products')->where('id', $p)->update(['preco_custo' => 99.0, 'preco_compra' => 99.0]);

        $vendaId = $this->venda(100.0);
        $this->item($vendaId, $p, 1, 60, '10.0000', '10.00', VendaItemCustoHistorico::SITUACAO_OK);
        $this->item($vendaId, $p, 1, 50, '10.0000', '10.00', VendaItemCustoHistorico::SITUACAO_OK);

        $payload = $this->agg();
        $this->assertFalse($payload['truncated']);
        $rows = $payload['rows'];
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertTrue($row->custo_disponivel);
        $this->assertSame(100.0, (float) $row->receita);
        $this->assertSame(20.0, (float) $row->custo);
        $this->assertSame(80.0, (float) $row->lucro);
    }

    public function test_rateio_inclui_item_sem_produto_como_na_lucratividade_por_venda(): void
    {
        $p = $this->produto(10.0, codigo: 'R1');
        $vendaId = $this->venda(100.0);
        // 80 + 30 = 110 vs total 100 → desconto 10 rateado nas duas bases.
        $this->item($vendaId, $p, 1, 80, '10.0000', '10.00', VendaItemCustoHistorico::SITUACAO_OK);
        DB::table('venda_itens')->insert([
            'venda_id' => $vendaId,
            'product_id' => null,
            'quantidade' => 1,
            'valor_item' => 30,
            'total' => 30,
            'custo_unitario' => null,
            'custo_total' => null,
            'custo_origem' => null,
            'custo_situacao' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->agg()['rows']->first();
        // Parte do produto: 80 - rateio(10*80/110) ≈ 72.73
        $this->assertEqualsWithDelta(72.73, (float) $row->receita, 0.01);
        $this->assertTrue($row->custo_disponivel);
        $this->assertSame(10.0, (float) $row->custo);
    }

    public function test_sem_captura_mostra_indisponivel_sem_fallback_nem_rodape_falso(): void
    {
        $p = $this->produto(15.0, codigo: 'B2');
        $vendaId = $this->venda(50.0);
        $this->item($vendaId, $p, 1, 50, null, null, null);

        $rows = $this->agg()['rows'];
        $row = $rows->first();
        $this->assertFalse($row->custo_disponivel);
        $this->assertNull($row->lucro);

        $mapped = $this->mapRows($rows);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $mapped[0]['lucro']);

        $totals = $this->totals($rows, truncated: false);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $totals['custo']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $totals['lucro']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $totals['margem']);
        $this->assertSame('50,00', $totals['receita']);
    }

    public function test_truncado_nao_apresenta_totais_como_completos(): void
    {
        // Simula payload truncado sem criar 5001 vendas.
        $p = $this->produto(2.0, codigo: 'T1');
        $v = $this->venda(20.0);
        $this->item($v, $p, 1, 20, '2.0000', '2.00', VendaItemCustoHistorico::SITUACAO_OK);
        $rows = $this->agg()['rows'];

        $totals = $this->totals($rows, truncated: true);
        $this->assertSame('TOTAL PARCIAL', $totals['codigo']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $totals['lucro']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $totals['custo']);
        $this->assertSame('20,00', $totals['receita']);
    }

    public function test_indisponivel_nao_distorce_ranking(): void
    {
        $pOk = $this->produto(1.0, codigo: 'OK');
        $pSem = $this->produto(1.0, codigo: 'SEM');
        $v1 = $this->venda(30.0);
        $this->item($v1, $pOk, 1, 30, '1.0000', '1.00', VendaItemCustoHistorico::SITUACAO_OK);
        $v2 = $this->venda(90.0);
        $this->item($v2, $pSem, 1, 90, null, null, null);

        $mapped = $this->mapRows($this->agg()['rows'], 'desc');
        $this->assertSame('OK', $mapped[0]['codigo']);
        $this->assertSame(LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL, $mapped[1]['lucro']);
    }

    public function test_devolucao_finalizada_abate_receita_e_custo(): void
    {
        $p = $this->produto(5.0, codigo: 'C3');
        $vendaId = $this->venda(40.0);
        $this->item($vendaId, $p, 2, 40, '5.0000', '10.00', VendaItemCustoHistorico::SITUACAO_OK);

        $devId = (int) DB::table('devolucoes_venda')->insertGetId([
            'empresa_id' => 1,
            'situacao' => 'finalizada',
            'data' => '2026-11-20',
            'venda_id' => $vendaId,
            'total' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('devolucao_venda_itens')->insert([
            'devolucao_venda_id' => $devId,
            'product_id' => $p,
            'qtd' => 1,
            'total' => 20,
            'custo_unitario' => 5,
            'custo_total' => 5,
            'custo_origem' => VendaItemCustoHistorico::ORIGEM_PRODUTO,
            'custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->agg()['rows']->first();
        $this->assertTrue($row->custo_disponivel);
        $this->assertSame(1.0, (float) $row->qtd);
        $this->assertSame(20.0, (float) $row->receita);
        $this->assertSame(5.0, (float) $row->custo);
        $this->assertSame(15.0, (float) $row->lucro);
    }

    public function test_canceladas_nao_entram(): void
    {
        $p = $this->produto(8.0, codigo: 'D4');
        $vendaId = (int) DB::table('vendas')->insertGetId([
            'empresa_id' => 1,
            'numero' => 'X'.uniqid(),
            'data' => '2026-11-10',
            'total' => 80,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => 'pedido',
            'plataforma' => 'erp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->item($vendaId, $p, 1, 80, '8.0000', '8.00', VendaItemCustoHistorico::SITUACAO_OK);

        $this->assertCount(0, $this->agg()['rows']);
    }

    public function test_tres_relatorios_preservam_slug_e_colunas(): void
    {
        $this->assertSame('produtos-lucratividade', (new ProdutosLucratividadeReport)->slug());
        $this->assertSame('produtos-mais-lucrativos', (new ProdutosMaisLucrativosReport)->slug());
        $this->assertSame('produtos-menos-lucrativos', (new ProdutosMenosLucrativosReport)->slug());
        $this->assertSame(
            ['codigo', 'descricao', 'grupo', 'qtd', 'receita', 'custo', 'lucro', 'margem'],
            (new ProdutosLucratividadeReport)->defaultColumns()
        );
    }

    /**
     * @return array{rows: Collection, truncated: bool, total_vendas: int, vendas_processadas: int, venda_limit: int}
     */
    private function agg(): array
    {
        return app(LucratividadeProdutosAggregator::class)
            ->aggregate(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapRows(Collection $rows, string $order = 'desc'): array
    {
        $report = new ProdutosLucratividadeReport;
        $ref = new ReflectionMethod($report, 'mapLucratividadeHistoricoRows');
        $ref->setAccessible(true);

        return $ref->invoke($report, $rows, $order);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, string>
     */
    private function totals(Collection $rows, bool $truncated): array
    {
        $report = new ProdutosLucratividadeReport;
        $ref = new ReflectionMethod($report, 'buildLucratividadeTotals');
        $ref->setAccessible(true);

        return $ref->invoke($report, $rows, $report->defaultColumns(), $truncated);
    }

    private function produto(float $custo, string $codigo): int
    {
        return (int) DB::table('products')->insertGetId([
            'codigo' => $codigo,
            'descricao' => 'PROD '.$codigo,
            'grupo' => 'G',
            'preco_custo' => $custo,
            'preco_compra' => $custo,
            'preco_venda' => 50,
            'is_servico' => false,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function venda(float $total): int
    {
        return (int) DB::table('vendas')->insertGetId([
            'empresa_id' => 1,
            'numero' => 'N'.uniqid(),
            'data' => '2026-11-15',
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => 'pedido',
            'plataforma' => 'erp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function item(
        int $vendaId,
        int $productId,
        float $qtd,
        float $total,
        ?string $custoUnit,
        ?string $custoTotal,
        ?string $situacao,
    ): void {
        DB::table('venda_itens')->insert([
            'venda_id' => $vendaId,
            'product_id' => $productId,
            'quantidade' => $qtd,
            'valor_item' => $qtd > 0 ? round($total / $qtd, 2) : 0,
            'total' => $total,
            'custo_unitario' => $custoUnit,
            'custo_total' => $custoTotal,
            'custo_origem' => $situacao === VendaItemCustoHistorico::SITUACAO_OK
                ? VendaItemCustoHistorico::ORIGEM_PRODUTO
                : null,
            'custo_situacao' => $situacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach (['devolucao_venda_itens', 'devolucoes_venda', 'venda_itens', 'vendas', 'products'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->nullable();
            $table->string('descricao', 120)->nullable();
            $table->string('grupo', 60)->nullable();
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_compra', 15, 4)->default(0);
            $table->decimal('preco_venda', 15, 2)->default(0);
            $table->boolean('is_servico')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40);
            $table->date('data');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20);
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venda_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('valor_item', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('custo_unitario', 15, 4)->nullable();
            $table->decimal('custo_total', 15, 2)->nullable();
            $table->string('custo_origem', 20)->nullable();
            $table->string('custo_situacao', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('devolucoes_venda', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('situacao', 20);
            $table->date('data')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('devolucao_venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('devolucao_venda_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('custo_unitario', 15, 4)->nullable();
            $table->decimal('custo_total', 15, 2)->nullable();
            $table->string('custo_origem', 20)->nullable();
            $table->string('custo_situacao', 20)->nullable();
            $table->timestamps();
        });
    }
}
