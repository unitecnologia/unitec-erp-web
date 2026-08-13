<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Unitec\FiscalEngine\Nfce\NfceCanceller;

final class NfceCancellerTest extends TestCase
{
    public function test_interpreta_cancelamento_autorizado_cstat_135(): void
    {
        $xml = <<<'XML'
<retEnvEvento versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <idLote>1</idLote>
  <tpAmb>1</tpAmb>
  <verAplic>SVRS2026</verAplic>
  <cOrgao>42</cOrgao>
  <cStat>128</cStat>
  <xMotivo>Lote de eventos processado</xMotivo>
  <retEvento versao="1.00">
    <infEvento>
      <tpAmb>1</tpAmb>
      <cOrgao>42</cOrgao>
      <cStat>135</cStat>
      <xMotivo>Cancelamento de NF-e homologado</xMotivo>
      <chNFe>42260722469772000100650010000000021333536687</chNFe>
      <tpEvento>110111</tpEvento>
      <nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2026-07-06T23:25:00-03:00</dhRegEvento>
      <nProt>142260000123456</nProt>
    </infEvento>
  </retEvento>
</retEnvEvento>
XML;

        $parsed = $this->parseRecepcaoEventoResponse($xml);

        $this->assertTrue($parsed['cancelada']);
        $this->assertSame('135', $parsed['codigo']);
        $this->assertSame('Cancelamento de NF-e homologado', $parsed['motivo']);
        $this->assertSame('142260000123456', $parsed['protocolo']);
        $this->assertStringContainsString('<retEvento', $parsed['retEventoXml']);
    }

    /**
     * @return array{cancelada: bool, codigo: string, motivo: string, protocolo: string, retEventoXml: string}
     */
    private function parseRecepcaoEventoResponse(string $xml): array
    {
        $method = new ReflectionMethod(NfceCanceller::class, 'parseRecepcaoEventoResponse');
        $method->setAccessible(true);

        /** @var array{cancelada: bool, codigo: string, motivo: string, protocolo: string, retEventoXml: string} $parsed */
        $parsed = $method->invoke(new NfceCanceller(), $xml);

        return $parsed;
    }
}
