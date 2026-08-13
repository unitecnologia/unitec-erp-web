<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;
use Unitec\FiscalEngine\Nfce\NfceConsultor;
use Unitec\FiscalEngine\Xml\NfceInutilizacaoXmlBuilder;

final class NfceConsultorInutilizacaoTest extends TestCase
{
    public function test_interpreta_consulta_autorizada_cstat_100(): void
    {
        $xml = <<<'XML'
<retConsSitNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>1</tpAmb>
  <verAplic>SVRS2026</verAplic>
  <cStat>100</cStat>
  <xMotivo>Autorizado o uso da NF-e</xMotivo>
  <protNFe versao="4.00">
    <infProt>
      <tpAmb>1</tpAmb>
      <verAplic>SVRS2026</verAplic>
      <chNFe>42260722469772000100650010000000021333536687</chNFe>
      <dhRecbto>2026-07-06T23:25:00-03:00</dhRecbto>
      <nProt>142260000123456</nProt>
      <digVal>abc</digVal>
      <cStat>100</cStat>
      <xMotivo>Autorizado o uso da NF-e</xMotivo>
    </infProt>
  </protNFe>
</retConsSitNFe>
XML;

        $parsed = $this->parseConsultaResponse($xml);

        $this->assertTrue($parsed['autorizada']);
        $this->assertSame('100', $parsed['codigo']);
        $this->assertSame('142260000123456', $parsed['protocolo']);
    }

    public function test_monta_xml_inutilizacao_com_id_correto(): void
    {
        $builder = new NfceInutilizacaoXmlBuilder();
        $request = new InutilizarNfceRequest(
            certificate: $this->createMock(\Unitec\FiscalEngine\Certificate\Certificate::class),
            cnpj: '22469772000100',
            tpAmb: 1,
            serie: 1,
            numeroInicial: 10,
            numeroFinal: 12,
            justificativa: 'Quebra de sequencia por falha operacional no PDV',
            dataEvento: new \DateTimeImmutable('2026-07-06'),
        );

        $built = $builder->build($request);
        $xml = $builder->finalizeInutNfe($built['dom']);

        $this->assertStringContainsString('<xServ>INUTILIZAR</xServ>', $xml);
        $this->assertStringContainsString('<nNFIni>10</nNFIni>', $xml);
        $this->assertStringContainsString('<nNFFin>12</nNFFin>', $xml);
        $this->assertStringStartsWith('ID422622469772000100065001000000010000000012', $built['infInutId']);
    }

    /**
     * @return array{codigo: string, motivo: string, protocolo: string, xml: string, autorizada: bool, cancelada: bool, denegada: bool}
     */
    private function parseConsultaResponse(string $xml): array
    {
        $method = new ReflectionMethod(NfceConsultor::class, 'parseConsultaResponse');
        $method->setAccessible(true);

        /** @var array{codigo: string, motivo: string, protocolo: string, xml: string, autorizada: bool, cancelada: bool, denegada: bool} $parsed */
        $parsed = $method->invoke(new NfceConsultor(), $xml, '42260722469772000100650010000000021333536687');

        return $parsed;
    }
}
