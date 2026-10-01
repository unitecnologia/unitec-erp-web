<?php

namespace Tests\Unit;

use App\Models\Nfe;
use App\Models\NfeItem;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class NfeDanfeValoresXmlTest extends TestCase
{
    public function test_danfe_com_desconto_usa_vprod_vdesc_vnf_do_xml_sem_somar_desconto(): void
    {
        $xml = file_get_contents(
            'c:\\Users\\Alencar\\Downloads\\NF-e n 267  DANFE (Anexos)\\2 - 42260962930316000130550010000002671017994093.xml'
        );
        $this->assertNotFalse($xml);

        // Simula DB com total líquido no item (bug antigo do DANFE lia isso como Valor Total).
        $nfe = $this->makeNfe([
            'xml' => $xml,
            'subtotal' => 45.00,
            'desconto' => 22.10,
            'total' => 22.90,
        ], [
            [
                'item' => 1,
                'quantidade' => 1,
                'valor_unitario' => 45,
                'total' => 22.90,
                'desconto' => 22.10,
                'descricao' => 'CANECA',
            ],
        ]);

        $data = (new NfeDanfeReportService)->buildViewData($nfe);

        $this->assertSame('45,00', $data['totais']['total_produtos']);
        $this->assertSame('22,10', $data['totais']['desconto']);
        $this->assertSame('22,90', $data['totais']['total_nota']);
        $this->assertSame('45,0000', $data['itens'][0]['valor_unit']);
        $this->assertSame('45,00', $data['itens'][0]['valor_total']);
        $this->assertSame('22,10', $data['itens'][0]['desconto']);
        $this->assertNotSame('67,10', $data['totais']['total_produtos']);
        $this->assertNotSame('22,90', $data['itens'][0]['valor_total']);
    }

    public function test_danfe_sem_desconto_mantem_vprod_igual_vnf(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <NFe xmlns="http://www.portalfiscal.inf.br/nfe">
    <infNFe Id="NFe42260962930316000130550010000000991000000001" versao="4.00">
      <det nItem="1">
        <prod>
          <cProd>1</cProd>
          <xProd>PRODUTO SEM DESC</xProd>
          <NCM>00000000</NCM>
          <CFOP>5102</CFOP>
          <uCom>UN</uCom>
          <qCom>2.0000</qCom>
          <vUnCom>10.0000</vUnCom>
          <vProd>20.00</vProd>
          <indTot>1</indTot>
        </prod>
      </det>
      <total>
        <ICMSTot>
          <vBC>0.00</vBC>
          <vICMS>0.00</vICMS>
          <vProd>20.00</vProd>
          <vFrete>0.00</vFrete>
          <vSeg>0.00</vSeg>
          <vDesc>0.00</vDesc>
          <vIPI>0.00</vIPI>
          <vPIS>0.00</vPIS>
          <vCOFINS>0.00</vCOFINS>
          <vOutro>0.00</vOutro>
          <vNF>20.00</vNF>
        </ICMSTot>
      </total>
    </infNFe>
  </NFe>
</nfeProc>
XML;

        $nfe = $this->makeNfe([
            'xml' => $xml,
            'subtotal' => 20.00,
            'desconto' => 0,
            'total' => 20.00,
        ], [
            [
                'item' => 1,
                'quantidade' => 2,
                'valor_unitario' => 10,
                'total' => 20.00,
                'desconto' => 0,
                'descricao' => 'PRODUTO SEM DESC',
            ],
        ]);

        $data = (new NfeDanfeReportService)->buildViewData($nfe);

        $this->assertSame('20,00', $data['totais']['total_produtos']);
        $this->assertSame('0,00', $data['totais']['desconto']);
        $this->assertSame('20,00', $data['totais']['total_nota']);
        $this->assertSame('20,00', $data['itens'][0]['valor_total']);
        $this->assertSame('0,00', $data['itens'][0]['desconto']);
    }

    public function test_fallback_sem_xml_nao_soma_desconto_no_total_produtos(): void
    {
        $nfe = $this->makeNfe([
            'xml' => null,
            'subtotal' => 45.00,
            'desconto' => 22.10,
            'total' => 22.90,
        ], [
            [
                'item' => 1,
                'quantidade' => 1,
                'valor_unitario' => 45,
                'total' => 22.90,
                'desconto' => 22.10,
                'descricao' => 'CANECA',
            ],
        ]);

        $data = (new NfeDanfeReportService)->buildViewData($nfe);

        $this->assertSame('45,00', $data['totais']['total_produtos']);
        $this->assertSame('22,10', $data['totais']['desconto']);
        $this->assertSame('22,90', $data['totais']['total_nota']);
        $this->assertSame('45,00', $data['itens'][0]['valor_total']);
    }

    public function test_item_row_usa_vprod_bruto_e_nao_total_liquido(): void
    {
        $item = new NfeItem([
            'quantidade' => 1,
            'valor_unitario' => 45,
            'total' => 22.90,
            'desconto' => 22.10,
            'descricao' => 'CANECA',
        ]);

        $method = new ReflectionMethod(NfeDanfeReportService::class, 'buildItemRow');
        $row = $method->invoke(new NfeDanfeReportService, $item);

        $this->assertSame('45,00', $row['valor_total']);
        $this->assertSame('22,10', $row['desconto']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $itens
     */
    private function makeNfe(array $attrs, array $itens): Nfe
    {
        $nfe = new class($attrs) extends Nfe
        {
            public Collection $itensRelation;

            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->itensRelation = new Collection;
            }

            public function load($relations): static
            {
                return $this;
            }

            public function getRelationValue($key)
            {
                if ($key === 'itens') {
                    return $this->itensRelation;
                }

                if ($key === 'faturas') {
                    return new Collection;
                }

                return parent::getRelationValue($key);
            }
        };

        $collection = new Collection;
        foreach ($itens as $itemAttrs) {
            $collection->push(new NfeItem($itemAttrs));
        }
        $nfe->itensRelation = $collection;

        return $nfe;
    }
}
