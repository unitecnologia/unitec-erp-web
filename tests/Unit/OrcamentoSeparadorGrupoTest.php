<?php

namespace Tests\Unit;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\Orcamento\OrcamentoReportService;
use App\Support\Erp\Orcamento\OrcamentoTotaisService;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class OrcamentoSeparadorGrupoTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_coluna_is_separador_existe_e_product_id_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('orcamento_itens', 'is_separador'));

        $columns = Schema::getColumnType('orcamento_itens', 'product_id');
        $this->assertNotNull($columns);
    }

    public function test_persistencia_ordem_e_totais_ignoram_separador(): void
    {
        $cliente = Person::query()->create([
            'nome_razao' => 'CLIENTE SEP',
            'tipo' => Person::TIPO_CLIENTE,
            'ativo' => true,
        ]);

        $vendedor = Vendedor::query()->create([
            'nome' => 'VEND SEP',
            'ativo' => true,
        ]);

        $produtoA = Product::query()->create([
            'codigo' => 'SEP-A',
            'descricao' => 'PECA DIANTEIRA',
            'ativo' => true,
            'preco_venda' => 10,
        ]);

        $produtoB = Product::query()->create([
            'codigo' => 'SEP-B',
            'descricao' => 'PECA TRASEIRA',
            'ativo' => true,
            'preco_venda' => 20,
        ]);

        $orcamento = Orcamento::query()->create([
            'numero' => '99001',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'subtotal' => 0,
            'desconto_valor' => 0,
            'percentual_desconto' => 0,
            'total' => 0,
            'status' => Orcamento::STATUS_ABERTO,
            'plataforma' => Orcamento::PLATAFORMA_ERP,
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $orcamento->id,
            'item' => 1,
            'is_separador' => true,
            'product_id' => null,
            'quantidade' => 0,
            'preco_unitario' => 0,
            'total' => 0,
            'desconto' => 0,
            'descricao' => 'SUSPENSAO DIANTEIRA',
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $orcamento->id,
            'item' => 2,
            'is_separador' => false,
            'product_id' => $produtoA->id,
            'quantidade' => 1,
            'preco_unitario' => 10,
            'total' => 10,
            'desconto' => 0,
            'descricao' => 'PECA DIANTEIRA',
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $orcamento->id,
            'item' => 3,
            'is_separador' => true,
            'product_id' => null,
            'quantidade' => 0,
            'preco_unitario' => 0,
            'total' => 0,
            'desconto' => 0,
            'descricao' => 'SUSPENSAO TRASEIRA',
        ]);

        OrcamentoItem::query()->create([
            'orcamento_id' => $orcamento->id,
            'item' => 4,
            'is_separador' => false,
            'product_id' => $produtoB->id,
            'quantidade' => 2,
            'preco_unitario' => 20,
            'total' => 40,
            'desconto' => 0,
            'descricao' => 'PECA TRASEIRA',
        ]);

        app(OrcamentoTotaisService::class)->recalcular($orcamento->fresh(['itens']));

        $orcamento->refresh();
        $this->assertSame(50.0, (float) $orcamento->subtotal);
        $this->assertSame(50.0, (float) $orcamento->total);

        $report = app(OrcamentoReportService::class);
        $loaded = $report->loadOrcamento($orcamento->fresh());
        $itens = $loaded->itens->values();

        $this->assertCount(4, $itens);
        $this->assertTrue($itens[0]->isSeparador());
        $this->assertSame('SUSPENSAO DIANTEIRA', $itens[0]->descricao);
        $this->assertSame(1, (int) $itens[0]->item);
        $this->assertFalse($itens[1]->isSeparador());
        $this->assertSame(2, (int) $itens[1]->item);
        $this->assertTrue($itens[2]->isSeparador());
        $this->assertSame(3, (int) $itens[2]->item);
        $this->assertFalse($itens[3]->isSeparador());
        $this->assertSame(4, (int) $itens[3]->item);

        $totais = $report->totaisImpressao($loaded);
        $this->assertSame(50.0, $totais['subtotal_bruto']);
        $this->assertSame(3.0, $totais['qtd_total']);
    }
}
