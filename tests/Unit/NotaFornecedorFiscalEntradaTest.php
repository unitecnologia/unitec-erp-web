<?php

namespace Tests\Unit;

use App\Models\CompraItem;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Models\NotaFornecedor;
use App\Models\NotaFornecedorItem;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDevolucaoFiscalService;
use App\Support\Erp\NotaFornecedor\NotaFornecedorFiscalSnapshotParser;
use App\Support\Erp\NotaFornecedor\NotaFornecedorItensSyncService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotaFornecedorFiscalEntradaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('devolucao_compra_itens');
        Schema::dropIfExists('devolucoes_compra');
        Schema::dropIfExists('compra_itens');
        Schema::dropIfExists('nota_fornecedor_itens');
        Schema::dropIfExists('notas_fornecedores');

        Schema::create('notas_fornecedores', function (Blueprint $table): void {
            $table->id();
            $table->longText('xml')->nullable();
            $table->timestamps();
        });

        Schema::create('nota_fornecedor_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('nota_fornecedor_id');
            $table->unsignedSmallInteger('n_item');
            $table->string('c_prod', 60)->nullable();
            $table->string('descricao', 255)->nullable();
            $table->decimal('quantidade', 15, 4)->default(0);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->json('fiscal_snapshot')->nullable();
            $table->boolean('fiscal_inconsistente')->default(false);
            $table->json('fiscal_snapshot_conflito')->nullable();
            $table->timestamp('fiscal_inconsistente_em')->nullable();
            $table->string('c_ean', 20)->nullable();
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 10)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->timestamps();
            $table->unique(['nota_fornecedor_id', 'n_item']);
        });

        Schema::create('compra_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('nota_fornecedor_item_id')->nullable();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('valor_unitario', 15, 4)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('devolucoes_compra', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->string('situacao')->default('aberta');
            $table->timestamps();
        });

        Schema::create('devolucao_compra_itens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('devolucao_compra_id');
            $table->unsignedBigInteger('compra_item_id')->nullable();
            $table->decimal('qtd', 15, 3)->default(0);
            $table->timestamps();
        });
    }

    public function test_parser_extrai_p_red_bc_e_ibscbs(): void
    {
        $parser = new NotaFornecedorFiscalSnapshotParser();
        $itens = $parser->parseItens($this->sampleXml());

        $this->assertNotNull($itens);
        $this->assertCount(1, $itens);
        $this->assertSame(1, $itens[0]['n_item']);
        $this->assertSame(10.0, $itens[0]['quantidade']);
        $this->assertSame(29.412, $itens[0]['fiscal_snapshot']['icms']['p_red_bc']);
        $this->assertSame('20', $itens[0]['fiscal_snapshot']['icms']['cst']);
        $this->assertSame('000001', $itens[0]['fiscal_snapshot']['ibscbs']['c_class_trib']);
    }

    public function test_rateio_mantem_percentuais_e_arredonda_valores(): void
    {
        $parser = new NotaFornecedorFiscalSnapshotParser();
        $snapshot = [
            'icms' => [
                'p_red_bc' => 29.412,
                'p_icms' => 17.0,
                'v_bc' => 100.0,
                'v_icms' => 11.9,
            ],
        ];

        $rateado = $parser->ratearSnapshot($snapshot, 10.0, 5.0);

        $this->assertSame(29.412, $rateado['icms']['p_red_bc']);
        $this->assertSame(17.0, $rateado['icms']['p_icms']);
        $this->assertSame(50.0, $rateado['icms']['v_bc']);
        $this->assertSame(5.95, $rateado['icms']['v_icms']);
    }

    public function test_sync_upsert_preserva_id_e_sinaliza_inconsistencia_quando_vinculado(): void
    {
        $nota = NotaFornecedor::query()->create(['xml' => $this->sampleXml()]);
        $sync = new NotaFornecedorItensSyncService();

        $sync->sync($nota);
        $item = NotaFornecedorItem::query()->where('nota_fornecedor_id', $nota->id)->first();
        $this->assertNotNull($item);
        $idOriginal = $item->id;

        CompraItem::query()->create([
            'compra_id' => 1,
            'product_id' => 1,
            'nota_fornecedor_item_id' => $item->id,
            'quantidade' => 10,
            'valor_unitario' => 10,
            'total' => 100,
        ]);

        $xmlAlterado = str_replace('<vICMS>11.90</vICMS>', '<vICMS>12.00</vICMS>', $this->sampleXml());
        $nota->forceFill(['xml' => $xmlAlterado])->save();

        $result = $sync->sync($nota->fresh());
        $item->refresh();

        $this->assertSame($idOriginal, $item->id);
        $this->assertSame(1, $result['inconsistencias']);
        $this->assertTrue($item->fiscal_inconsistente);
        $this->assertSame(11.9, (float) $item->fiscal_snapshot['icms']['v_icms']);
        $this->assertSame(12.0, (float) $item->fiscal_snapshot_conflito['icms']['v_icms']);
    }

    public function test_devolucao_acumulada_bloqueia_acima_do_original(): void
    {
        $notaItem = NotaFornecedorItem::query()->create([
            'nota_fornecedor_id' => NotaFornecedor::query()->create([])->id,
            'n_item' => 1,
            'quantidade' => 10,
            'valor_unitario' => 1,
            'valor_total' => 10,
            'fiscal_snapshot' => ['icms' => ['p_icms' => 17, 'v_bc' => 100, 'v_icms' => 17]],
        ]);

        $compraItem = CompraItem::query()->create([
            'compra_id' => 1,
            'product_id' => 1,
            'nota_fornecedor_item_id' => $notaItem->id,
            'quantidade' => 10,
            'valor_unitario' => 1,
            'total' => 10,
        ]);

        $dev = DevolucaoCompra::query()->create([
            'compra_id' => 1,
            'situacao' => DevolucaoCompra::SITUACAO_FINALIZADA,
        ]);

        DevolucaoCompraItem::query()->create([
            'devolucao_compra_id' => $dev->id,
            'compra_item_id' => $compraItem->id,
            'qtd' => 4,
        ]);

        $service = new NotaFornecedorDevolucaoFiscalService();

        $this->assertSame(10.0, $service->quantidadeOriginal($compraItem));
        $this->assertSame(4.0, $service->quantidadeJaDevolvida($compraItem));
        $this->assertSame(6.0, $service->quantidadeDisponivel($compraItem));

        $service->assertQuantidadePermitida($compraItem, 6.0);

        $this->expectException(DomainException::class);
        $service->assertQuantidadePermitida($compraItem, 6.1);
    }

    public function test_overrides_nfe_usam_rateio_do_snapshot(): void
    {
        $notaItem = NotaFornecedorItem::query()->create([
            'nota_fornecedor_id' => NotaFornecedor::query()->create([])->id,
            'n_item' => 1,
            'quantidade' => 10,
            'ncm' => '22021000',
            'valor_unitario' => 10,
            'valor_total' => 100,
            'fiscal_snapshot' => [
                'icms' => [
                    'cst' => '20',
                    'p_red_bc' => 29.412,
                    'p_icms' => 17.0,
                    'v_bc' => 100.0,
                    'v_icms' => 11.9,
                    'mod_bc' => '3',
                ],
                'inf_ad_prod' => 'INFO',
            ],
        ]);

        $overrides = (new NotaFornecedorDevolucaoFiscalService())->montarOverridesNfe($notaItem, 5.0);

        $this->assertSame(50.0, $overrides['base_icms']);
        $this->assertSame(5.95, $overrides['valor_icms']);
        $this->assertSame(29.412, $overrides['p_red_bc_icms']);
        $this->assertSame(17.0, $overrides['aliq_icms']);
        // Sem empresa → trata como Simples: CSOSN 900, sem CST da entrada.
        $this->assertSame('900', $overrides['csosn']);
        $this->assertArrayNotHasKey('cst', $overrides);
        $this->assertSame('99', $overrides['cst_pis']);
        $this->assertSame(0.0, $overrides['valor_pis_icms']);
        $this->assertSame('INFO', $overrides['info_adicionais']);
        $this->assertTrue($overrides['devolucao_compra_fiscal']);
    }

    private function sampleXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe42260811315435000494550010001404501707183710">
      <det nItem="1">
        <prod>
          <cProd>ABC</cProd>
          <cEAN>SEM GTIN</cEAN>
          <xProd>PRODUTO TESTE</xProd>
          <NCM>22021000</NCM>
          <CFOP>5102</CFOP>
          <uCom>UN</uCom>
          <qCom>10.0000</qCom>
          <vUnCom>10.0000000000</vUnCom>
          <vProd>100.00</vProd>
          <cEANTrib>SEM GTIN</cEANTrib>
          <uTrib>UN</uTrib>
          <qTrib>10.0000</qTrib>
          <vUnTrib>10.0000000000</vUnTrib>
        </prod>
        <imposto>
          <ICMS>
            <ICMS20>
              <orig>0</orig>
              <CST>20</CST>
              <modBC>3</modBC>
              <pRedBC>29.4120</pRedBC>
              <vBC>100.00</vBC>
              <pICMS>17.0000</pICMS>
              <vICMS>11.90</vICMS>
            </ICMS20>
          </ICMS>
          <PIS><PISAliq><CST>01</CST><vBC>100.00</vBC><pPIS>1.6500</pPIS><vPIS>1.65</vPIS></PISAliq></PIS>
          <COFINS><COFINSAliq><CST>01</CST><vBC>100.00</vBC><pCOFINS>7.6000</pCOFINS><vCOFINS>7.60</vCOFINS></COFINSAliq></COFINS>
          <IBSCBS>
            <CST>000</CST>
            <cClassTrib>000001</cClassTrib>
            <gIBSCBS>
              <vBC>100.00</vBC>
              <gIBSUF><pIBSUF>0.1000</pIBSUF><vIBSUF>0.10</vIBSUF></gIBSUF>
              <gCBS><pCBS>0.9000</pCBS><vCBS>0.90</vCBS></gCBS>
              <vIBS>0.10</vIBS>
            </gIBSCBS>
          </IBSCBS>
        </imposto>
      </det>
    </infNFe>
  </NFe>
</nfeProc>
XML;
    }
}
