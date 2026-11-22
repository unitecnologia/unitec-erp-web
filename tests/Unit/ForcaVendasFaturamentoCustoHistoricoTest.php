<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Dre\VendaItemCustoHistorico;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pontual: Tela de Venda e Força de Vendas compartilham ForcaVendasFaturamentoService.
 * Valida a gravação D2.6 em venda_itens no mesmo padrão do PDV (lote + preserva).
 */
class ForcaVendasFaturamentoCustoHistoricoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_tv_e_fv_gravam_custo_ok_indisponivel_e_servico_em_lote(): void
    {
        $pOk = $this->produto(12.50);
        $pZero = $this->produto(0.0);
        $pServ = $this->produto(8.0, servico: true);

        $t0 = hrtime(true);
        $lote = VendaItemCustoHistorico::resolverLote(
            [(int) $pOk->id, (int) $pZero->id, (int) $pServ->id],
            1,
        );
        $ms = (hrtime(true) - $t0) / 1e6;

        $this->assertCount(3, $lote);
        $this->assertLessThan(100.0, $ms, 'resolverLote em lote deve permanecer leve');

        foreach ([Venda::PLATAFORMA_ERP, Venda::PLATAFORMA_MOBILE] as $plataforma) {
            $vendaId = $this->vendaFechada(1, '2026-11-19', 100, $plataforma);

            $itens = [
                ['product' => $pOk, 'qtd' => 2.0, 'total' => 40.0],
                ['product' => $pZero, 'qtd' => 1.0, 'total' => 20.0],
                ['product' => $pServ, 'qtd' => 1.0, 'total' => 40.0],
            ];

            foreach ($itens as $row) {
                /** @var Product $product */
                $product = $row['product'];
                $snap = VendaItemCustoHistorico::dePreservadoOuResolver(
                    (int) $product->id,
                    (float) $row['qtd'],
                    1,
                    [],
                    $product,
                    $lote,
                );

                VendaItem::query()->create([
                    'venda_id' => $vendaId,
                    'product_id' => $product->id,
                    'quantidade' => $row['qtd'],
                    'valor_item' => round($row['total'] / $row['qtd'], 2),
                    'total' => $row['total'],
                    'comissao_pct' => 0,
                    ...$snap,
                ]);
            }

            $gravados = VendaItem::query()->where('venda_id', $vendaId)->orderBy('id')->get();
            $this->assertCount(3, $gravados, $plataforma);

            $this->assertSame(VendaItemCustoHistorico::SITUACAO_OK, (string) $gravados[0]->custo_situacao);
            $this->assertSame('12.5000', (string) $gravados[0]->custo_unitario);
            $this->assertSame('25.00', (string) $gravados[0]->custo_total);

            // Regra atual: custo zero cadastral → Indisponível (não inventa 0 ok).
            $this->assertSame(VendaItemCustoHistorico::SITUACAO_INDISPONIVEL, (string) $gravados[1]->custo_situacao);
            $this->assertNull($gravados[1]->custo_unitario);
            $this->assertNull($gravados[1]->custo_total);

            $this->assertSame(VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL, (string) $gravados[2]->custo_situacao);

            // Desconto comercial não entra no snapshot (total item 40 com custo 25).
            $this->assertSame('25.00', (string) $gravados[0]->custo_total);
            $this->assertSame(40.0, (float) $gravados[0]->total);
        }
    }

    public function test_refaturamento_preserva_custo_historico_por_produto(): void
    {
        $p = $this->produto(7.00);
        $vendaId = $this->vendaFechada(1, '2026-11-19', 70, Venda::PLATAFORMA_ERP);

        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 1,
            'valor_item' => 70,
            'total' => 70,
            'comissao_pct' => 0,
            ...VendaItemCustoHistorico::snapshot((int) $p->id, 1.0, 1, $p),
        ]);

        Product::query()->whereKey($p->id)->update(['preco_custo' => 99.0, 'preco_compra' => 99.0]);

        $preservado = VendaItemCustoHistorico::mapaPreservarPorProduto($vendaId);
        VendaItem::query()->where('venda_id', $vendaId)->delete();

        $lote = VendaItemCustoHistorico::resolverLote([(int) $p->id], 1);
        $snap = VendaItemCustoHistorico::dePreservadoOuResolver(
            (int) $p->id,
            2.0,
            1,
            $preservado,
            $p->fresh(),
            $lote,
        );

        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 2,
            'valor_item' => 35,
            'total' => 70,
            'comissao_pct' => 0,
            ...$snap,
        ]);

        $item = VendaItem::query()->where('venda_id', $vendaId)->first();
        $this->assertSame('7.0000', (string) $item->custo_unitario);
        $this->assertSame('14.00', (string) $item->custo_total);
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_OK, (string) $item->custo_situacao);
    }

    public function test_refaturamento_preserva_indisponivel_e_nao_injeta_custo_posterior(): void
    {
        $p = $this->produto(0.0);
        Product::query()->whereKey($p->id)->update(['preco_custo' => 0, 'preco_compra' => 0]);
        $vendaId = $this->vendaFechada(1, '2026-11-19', 40, Venda::PLATAFORMA_ERP);

        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 2,
            'valor_item' => 20,
            'total' => 40,
            'comissao_pct' => 0,
            ...VendaItemCustoHistorico::snapshot((int) $p->id, 2.0, 1, $p->fresh()),
        ]);

        $antes = VendaItem::query()->where('venda_id', $vendaId)->first();
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_INDISPONIVEL, (string) $antes->custo_situacao);

        // Cadastro corrigido depois do fechamento — refaturamento NÃO pode tratar como histórico original.
        Product::query()->whereKey($p->id)->update(['preco_custo' => 8.5, 'preco_compra' => 8.0]);
        $preservado = VendaItemCustoHistorico::mapaPreservarPorProduto($vendaId);
        $this->assertArrayHasKey((int) $p->id, $preservado);
        $this->assertSame(
            VendaItemCustoHistorico::SITUACAO_INDISPONIVEL,
            (string) $preservado[(int) $p->id]['custo_situacao']
        );

        VendaItem::query()->where('venda_id', $vendaId)->delete();
        $lote = VendaItemCustoHistorico::resolverLote([(int) $p->id], 1);
        $this->assertNotNull($lote[(int) $p->id]['unitario'] ?? null, 'lote atual teria custo — não deve ser usado');

        $snap = VendaItemCustoHistorico::dePreservadoOuResolver(
            (int) $p->id,
            2.0,
            1,
            $preservado,
            $p->fresh(),
            $lote,
        );

        VendaItem::query()->create([
            'venda_id' => $vendaId,
            'product_id' => $p->id,
            'quantidade' => 2,
            'valor_item' => 20,
            'total' => 40,
            'comissao_pct' => 0,
            ...$snap,
        ]);

        $depois = VendaItem::query()->where('venda_id', $vendaId)->first();
        $this->assertSame(VendaItemCustoHistorico::SITUACAO_INDISPONIVEL, (string) $depois->custo_situacao);
        $this->assertNull($depois->custo_unitario);
        $this->assertNull($depois->custo_total);
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

    private function vendaFechada(int $empresaId, string $data, float $total, string $plataforma): int
    {
        return (int) DB::table('vendas')->insertGetId([
            'empresa_id' => $empresaId,
            'numero' => 'N'.uniqid(),
            'data' => $data,
            'cliente_id' => 1,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => 'pedido',
            'plataforma' => $plataforma,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach (['venda_itens', 'vendas', 'products', 'product_empresa_precos'] as $t) {
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
    }
}
