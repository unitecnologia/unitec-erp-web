<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Unitec\FiscalEngine\Nfe\DfeDistribuidor;
use Unitec\FiscalEngine\Nfe\DfeDocumentoParser;
use Unitec\FiscalEngine\Nfe\DistribuicaoDfeSoapClient;

final class DistribuicaoDfeTest extends TestCase
{
    public function test_normaliza_nsu_com_15_digitos(): void
    {
        $this->assertSame('000000000000123', DfeDistribuidor::normalizarNsu('123'));
        $this->assertSame('000000000123456', DfeDistribuidor::normalizarNsu('000000000123456'));
    }

    public function test_monta_envelope_soap_distribuicao_dfe(): void
    {
        $client = new DistribuicaoDfeSoapClient();
        $xml = <<<'XML'
<distDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>2</tpAmb>
  <cUFAutor>42</cUFAutor>
  <CNPJ>22469772000100</CNPJ>
  <distNSU><ultNSU>000000000000000</ultNSU></distNSU>
</distDFeInt>
XML;

        $envelope = $client->buildEnvelope($xml);

        $this->assertStringContainsString('nfeDistDFeInteresse', $envelope);
        $this->assertStringContainsString('<ultNSU>000000000000000</ultNSU>', $envelope);
    }

    public function test_monta_xml_consulta_por_chave(): void
    {
        $chave = '42260122469772000100550010000152301234567890';
        $method = new ReflectionMethod(DfeDistribuidor::class, 'buildDistDfeInt');
        $method->setAccessible(true);

        /** @var string $xml */
        $xml = $method->invoke(new DfeDistribuidor(), 2, '42', '22469772000100', '000000000000000', $chave);

        $this->assertStringContainsString('<consChNFe>', $xml);
        $this->assertStringContainsString('<chNFe>' . $chave . '</chNFe>', $xml);
        $this->assertStringNotContainsString('<distNSU>', $xml);
    }

    public function test_interpreta_res_nfe_modelo_55(): void
    {
        $xml = <<<'XML'
<resNFe xmlns="http://www.portalfiscal.inf.br/nfe">
  <chNFe>42260122469772000100550010000152301234567890</chNFe>
  <CNPJ>22469772000100</CNPJ>
  <xNome>DISTRIBUIDORA CENTRAL LTDA</xNome>
  <IE>123456789</IE>
  <dhEmi>2026-01-05T10:15:00-03:00</dhEmi>
  <tpNF>1</tpNF>
  <vNF>4580.75</vNF>
  <dhRecbto>2026-01-06T08:20:00-03:00</dhRecbto>
  <nProt>142260000123456</nProt>
</resNFe>
XML;

        $gzip = gzencode($xml);
        $this->assertNotFalse($gzip);

        $parser = new DfeDocumentoParser();
        $resumo = $parser->parseDocZip('000000000000001', 'resNFe_v1.01.xsd', base64_encode($gzip));

        $this->assertNotNull($resumo);
        $this->assertSame('42260122469772000100550010000152301234567890', $resumo->chave);
        $this->assertSame('22469772000100', $resumo->cnpj);
        $this->assertSame('15230', $resumo->numero);
        $this->assertSame(4580.75, $resumo->total);
    }

    public function test_parse_resposta_distribuicao_com_documentos(): void
    {
        $resNfe = <<<'XML'
<resNFe xmlns="http://www.portalfiscal.inf.br/nfe">
  <chNFe>42260122469772000100550010000152301234567890</chNFe>
  <CNPJ>22469772000100</CNPJ>
  <xNome>DISTRIBUIDORA CENTRAL LTDA</xNome>
  <dhEmi>2026-01-05T10:15:00-03:00</dhEmi>
  <tpNF>1</tpNF>
  <vNF>100.00</vNF>
  <dhRecbto>2026-01-06T08:20:00-03:00</dhRecbto>
</resNFe>
XML;
        $docZip = base64_encode((string) gzencode($resNfe));

        $retorno = <<<XML
<retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>2</tpAmb>
  <verAplic>1.7.6</verAplic>
  <cStat>137</cStat>
  <xMotivo>Documento localizado</xMotivo>
  <dhResp>2026-01-06T10:00:00-03:00</dhResp>
  <ultNSU>000000000000001</ultNSU>
  <maxNSU>000000000000001</maxNSU>
  <loteDistDFeInt>
    <docZip NSU="000000000000001" schema="resNFe_v1.01.xsd">{$docZip}</docZip>
  </loteDistDFeInt>
</retDistDFeInt>
XML;

        $soap = '<soap:Envelope><soap:Body><nfeDistDFeInteresseResult>' . $retorno . '</nfeDistDFeInteresseResult></soap:Body></soap:Envelope>';

        $method = new ReflectionMethod(DfeDistribuidor::class, 'parseResponse');
        $method->setAccessible(true);

        /** @var \Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeResponse $response */
        $response = $method->invoke(new DfeDistribuidor(), $soap);

        $this->assertSame('137', $response->statusCodigo);
        $this->assertCount(1, $response->documentos);
        $this->assertSame('15230', $response->documentos[0]->numero);
    }

    public function test_interpreta_consulta_situacao_nfe(): void
    {
        $chave = '42260122469772000100550010000152301234567890';
        $xml = <<<XML
<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
  <tpAmb>2</tpAmb>
  <verAplic>SVRSnfce</verAplic>
  <cStat>100</cStat>
  <xMotivo>Autorizado o uso da NF-e</xMotivo>
  <protNFe versao="4.00">
    <infProt>
      <tpAmb>2</tpAmb>
      <verAplic>SVRSnfce</verAplic>
      <chNFe>{$chave}</chNFe>
      <dhRecbto>2026-01-06T08:20:00-03:00</dhRecbto>
      <nProt>142260000123456</nProt>
      <cStat>100</cStat>
      <xMotivo>Autorizado o uso da NF-e</xMotivo>
    </infProt>
  </protNFe>
</retConsSitNFe>
XML;

        $parser = new DfeDocumentoParser();
        $resumo = $parser->parseFromConsultaXml($xml, $chave);

        $this->assertNotNull($resumo);
        $this->assertSame($chave, $resumo->chave);
        $this->assertSame('15230', $resumo->numero);
        $this->assertSame('consulta', $resumo->nsu);
    }
}
