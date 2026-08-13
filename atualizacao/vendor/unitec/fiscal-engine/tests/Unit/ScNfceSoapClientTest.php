<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Nfce\ScNfceSoapClient;

final class ScNfceSoapClientTest extends TestCase
{
    public function test_envelope_inclui_cabecalho_nfe_cabec_msg(): void
    {
        $client = new ScNfceSoapClient();
        $payload = '<enviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . '<idLote>000000000000001</idLote><indSinc>1</indSinc></enviNFe>';

        $envelope = $client->buildAutorizacaoEnvelope($payload, '42');

        $this->assertStringContainsString('<soap12:Header>', $envelope);
        $this->assertStringContainsString(
            '<nfeCabecMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4">',
            $envelope,
        );
        $this->assertStringContainsString('<cUF>42</cUF>', $envelope);
        $this->assertStringContainsString('<versaoDados>4.00</versaoDados>', $envelope);
        $this->assertStringContainsString('<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4">', $envelope);
        $this->assertStringNotContainsString('<?xml', str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $envelope));
    }
}
