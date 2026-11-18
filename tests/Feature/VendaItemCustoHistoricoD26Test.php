<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Dre\DreCmvHistoricoService;
use App\Support\Erp\Dre\DreDemonstrativoGerencialService;
use App\Support\Erp\Dre\DreReceitasGerenciaisService;
use App\Support\Erp\Dre\VendaItemCustoHistorico;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendaItemCustoHistoricoD26Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_snapshot_grava_custo_e_preserva_apos_mudanca_cadastro(): void
    {
        $p = $this->produto(10.00);
        $snap = VendaItemCustoHistorico::snapshot((int) $p->id, 2.0, 1, $p);
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_OK, $snap['custo_situacao']);
        $this->assertSame('10.0000', $snap['custo_unitario']);
        $this->assertSame('20.00', $snap['custo_total']);

        $vendaId = $this->vendaFechada(1, '2026-11-10', 100);
        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 2,
            'valor_item' => 50,
            'total' => 100,
            ...$snap,
        ]);

        // Altera cadastro — histórico da venda não muda.
        Product::query()->whereKey($p->id)->update(['preco_custo' => 99.00]);
        $item = VendaItem::query()->where('venda_id', $vendaId)->first();
        $this->assertSame('10.0000', (string) $item->custo_unitario);
        $this->assertSame('20.00', (string) $item->custo_total);
    }

    public function test_produto_sem_custo_nao_usa_zero(): void
    {
        $p = $this->produto(0);
        Product::query()->whereKey($p->id)->update(['preco_custo' => 0, 'preco_compra' => 0]);
        $snap = VendaItemCustoHistorico::snapshot((int) $p->id, 1.0, 1);
        $this->assertNull($snap['custo_unitario']);
        $this->assertNull($snap['custo_total']);
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_INDISPONIVEL, $snap['custo_situacao']);
    }

    public function test_servico_nao_aplicavel(): void
    {
        $p = $this->produto(5.00, servico: true);
        $snap = VendaItemCustoHistorico::snapshot((int) $p->id, 1.0, 1, $p);
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL, $snap['custo_situacao']);
        $this->assertNull($snap['custo_unitario']);
    }

    public function test_preservar_em_reprocessamento(): void
    {
        $p = $this->produto(7.50);
        $vendaId = $this->vendaFechada(1, '2026-11-11', 30);
        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 1,
            'valor_item' => 30,
            'total' => 30,
            ...VendaItemCustoHistorico::snapshot((int) $p->id, 1.0, 1, $p),
        ]);

        Product::query()->whereKey($p->id)->update(['preco_custo' => 50]);
        $mapa = VendaItemCustoHistorico::mapaPreservarPorProduto($vendaId);
        $novo = VendaItemCustoHistorico::dePreservadoOuResolver((int) $p->id, 1.0, 1, $mapa, $p->fresh());
        $this->assertSame('7.5000', $novo['custo_unitario']);
    }

    public function test_cmv_so_libera_com_cobertura_completa(): void
    {
        $pOk = $this->produto(10);
        $pSem = $this->produto(0);
        Product::query()->whereKey($pSem->id)->update(['preco_custo' => 0, 'preco_compra' => 0]);

        $v1 = $this->vendaFechada(1, '2026-11-12', 50);
        VendaItem::query()->create([
            'venda_id' => $v1,
            'product_id' => $pOk->id,
            'quantidade' => 1,
            'valor_item' => 50,
            'total' => 50,
            ...VendaItemCustoHistorico::snapshot((int) $pOk->id, 1, 1, $pOk),
        ]);
        VendaItem::query()->create([
            'venda_id' => $v1,
            'product_id' => $pSem->id,
            'quantidade' => 1,
            'valor_item' => 10,
            'total' => 10,
            ...VendaItemCustoHistorico::snapshot((int) $pSem->id, 1, 1, $pSem->fresh()),
        ]);

        $parcial = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertFalse($parcial['cmv_disponivel']);
        $this->assertNull($parcial['cmv']);
        $this->assertGreaterThan(0, $parcial['itens_indisponivel']);

        // Remove item sem custo → cobertura completa.
        VendaItem::query()->where('product_id', $pSem->id)->delete();
        $ok = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertTrue($ok['cmv_disponivel']);
        $this->assertSame('10.00', $ok['cmv']);
    }

    public function test_venda_antiga_sem_captura_bloqueia_cmv(): void
    {
        $p = $this->produto(8);
        $v = $this->vendaFechada(1, '2026-11-05', 40);
        VendaItem::query()->create([
            'venda_id' => $v,
            'product_id' => $p->id,
            'quantidade' => 1,
            'valor_item' => 40,
            'total' => 40,
            // sem custo_situacao = venda antiga
        ]);

        $r = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertFalse($r['cmv_disponivel']);
        $this->assertSame(1, $r['itens_sem_captura']);
    }

    public function test_cancelada_nao_entra_no_cmv(): void
    {
        $p = $this->produto(5);
        $id = DB::table('vendas')->insertGetId([
            'empresa_id' => 1,
            'numero' => 'C1',
            'data' => '2026-11-08',
            'cliente_id' => 1,
            'total' => 20,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => 'pedido',
            'plataforma' => 'erp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        VendaItem::query()->create([
            'venda_id' => $id,
            'product_id' => $p->id,
            'quantidade' => 1,
            'valor_item' => 20,
            'total' => 20,
            ...VendaItemCustoHistorico::snapshot((int) $p->id, 1, 1, $p),
        ]);

        $r = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertSame(0, $r['vendas_fechadas']);
        $this->assertFalse($r['cmv_disponivel']);
    }

    public function test_isolamento_empresa_e_demonstrativo_resultado_bruto(): void
    {
        $p = $this->produto(4);
        $v1 = $this->vendaFechada(1, '2026-11-09', 20);
        $v2 = $this->vendaFechada(2, '2026-11-09', 80);
        foreach ([[$v1, 20], [$v2, 80]] as [$vid, $tot]) {
            VendaItem::query()->create([
                'venda_id' => $vid,
                'product_id' => $p->id,
                'quantidade' => 1,
                'valor_item' => $tot,
                'total' => $tot,
                ...VendaItemCustoHistorico::snapshot((int) $p->id, 1, 1, $p),
            ]);
        }

        $e1 = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $e2 = app(DreCmvHistoricoService::class)->calcular(2, '2026-11-01', '2026-11-30');
        $this->assertSame('4.00', $e1['cmv']);
        $this->assertSame('4.00', $e2['cmv']);

        $rec = [
            'empresa_id' => 1,
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-30',
            'receita_bruta_operacional' => '20.00',
            'devolucoes' => '0.00',
            'receita_liquida_operacional' => '20.00',
            'descontos_embutidos_informativo' => '0.00',
        ];
        $demo = app(DreDemonstrativoGerencialService::class)->montar($rec, [
            'cmv_historico' => $e1,
            'custos_competencia' => '0.00',
            'despesas_operacionais' => '0.00',
            'despesas_administrativas' => '0.00',
            'despesas_comerciais' => '0.00',
            'despesas_financeiras_competencia' => '0.00',
            'dre_parametros' => [
                'exibir_tributos' => false,
                'calcular_estimativa_simples' => false,
                'aliquota_simples_pct' => null,
                'crt' => 1,
                'regime' => 'simples',
                'permite_estimativa_simples' => true,
            ],
            'tributos_informativos' => [
                'disponivel' => false,
                'pendencia' => 'Sem NF-e no teste',
                'total_destacado' => null,
            ],
        ]);
        $this->assertTrue($demo['cmv_disponivel']);
        $this->assertSame('4.00', $demo['cmv']);
        $this->assertTrue($demo['resultado_bruto_disponivel']);
        $this->assertSame('16.00', $demo['resultado_bruto']);
        $this->assertStringContainsString('Parcial', (string) $demo['resultado_bruto_pendencia']);
        $this->assertFalse($demo['impostos_disponivel']);
        $this->assertTrue($demo['resultado_operacional_disponivel']);
        $this->assertSame('16.00', $demo['resultado_operacional']);
        $this->assertStringContainsString('Gerencial', (string) $demo['resultado_operacional_pendencia']);
    }

    public function test_offline_payload_preserva_custo_da_venda_nao_do_sync(): void
    {
        $p = $this->produto(10.00);
        Product::query()->whereKey($p->id)->update(['preco_custo' => 99.00]);

        $snap = VendaItemCustoHistorico::dePayloadOfflineOuSnapshot(
            [
                'custo_unitario' => '10.0000',
                'custo_total' => '20.00',
                'custo_origem' => VendaItemCustoHistorico::ORIGEM_PRODUTO,
                'custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK,
            ],
            (int) $p->id,
            2.0,
            1,
            $p->fresh(),
        );

        $this->assertSame('10.0000', $snap['custo_unitario']);
        $this->assertSame('20.00', $snap['custo_total']);
        $this->assertNotSame('99.0000', $snap['custo_unitario']);
    }

    public function test_devolucao_reduz_cmv_liquido_sem_duplicar(): void
    {
        $p = $this->produto(10);
        $v = $this->vendaFechada(1, '2026-11-12', 50);
        VendaItem::query()->create([
            'venda_id' => $v,
            'product_id' => $p->id,
            'quantidade' => 2,
            'valor_item' => 25,
            'total' => 50,
            ...VendaItemCustoHistorico::snapshot((int) $p->id, 2, 1, $p),
        ]);

        $devId = DB::table('devolucoes_venda')->insertGetId([
            'empresa_id' => 1,
            'data' => '2026-11-13',
            'situacao' => 'finalizada',
            'total' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('devolucao_venda_itens')->insert([
            'devolucao_venda_id' => $devId,
            'product_id' => $p->id,
            'qtd' => 1,
            'custo_unitario' => '10.0000',
            'custo_total' => '10.00',
            'custo_origem' => VendaItemCustoHistorico::ORIGEM_PRODUTO,
            'custo_situacao' => VendaItemCustoHistorico::SITUACAO_OK,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = app(DreCmvHistoricoService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertTrue($r['cmv_disponivel']);
        $this->assertSame('20.00', $r['cmv']);
        $this->assertSame('10.00', $r['cmv_devolucoes']);
        $this->assertSame('10.00', $r['cmv_liquido']);
    }

    public function test_regressao_receitas_d21(): void
    {
        $this->vendaFechada(1, '2026-11-10', 55);
        $r = app(DreReceitasGerenciaisService::class)->calcular(1, '2026-11-01', '2026-11-30');
        $this->assertSame('55.00', $r['receita_liquida_operacional']);
    }

    private function produto(float $custo, bool $servico = false): Product
    {
        return Product::query()->create([
            'codigo' => 'P'.uniqid(),
            'descricao' => 'PROD',
            'preco_custo' => $custo,
            'preco_compra' => $custo > 0 ? $custo : 0,
            'preco_venda' => 50,
            'is_servico' => $servico,
            'ativo' => true,
        ]);
    }

    private function vendaFechada(int $empresaId, string $data, float $total): int
    {
        return (int) DB::table('vendas')->insertGetId([
            'empresa_id' => $empresaId,
            'numero' => 'N'.uniqid(),
            'data' => $data,
            'cliente_id' => 1,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => 'pedido',
            'plataforma' => 'erp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach (['venda_itens', 'vendas', 'products', 'product_empresa_precos', 'devolucao_venda_itens', 'devolucoes_venda'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->nullable();
            $table->string('descricao', 120)->nullable();
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_compra', 15, 4)->default(0);
            $table->decimal('preco_venda', 15, 2)->default(0);
            $table->boolean('is_servico')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('product_empresa_precos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('preco_custo', 15, 4)->nullable();
            $table->decimal('preco_compra', 15, 4)->nullable();
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
        Schema::create('venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venda_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('valor_item', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('comissao_pct', 8, 2)->nullable();
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
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('devolucao_venda_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('devolucao_venda_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('venda_item_id')->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->decimal('preco', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('custo_unitario', 15, 4)->nullable();
            $table->decimal('custo_total', 15, 2)->nullable();
            $table->string('custo_origem', 20)->nullable();
            $table->string('custo_situacao', 20)->nullable();
            $table->timestamps();
        });
    }
}
