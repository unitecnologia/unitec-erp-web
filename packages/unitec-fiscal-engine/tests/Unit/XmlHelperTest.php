<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Util\XmlHelper;

final class XmlHelperTest extends TestCase
{
    public function test_compact_remove_espacos_entre_tags(): void
    {
        $xml = "<root>\n  <a>1</a>\n  <b>2</b>\n</root>";

        $this->assertSame('<root><a>1</a><b>2</b></root>', XmlHelper::compact($xml));
    }

    public function test_ensure_signature_is_last(): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML(
            '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
            . '<infNFe Id="NFe1" versao="4.00"><ide/></infNFe>'
            . '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo/></Signature>'
            . '<infNFeSupl><qrCode>url</qrCode></infNFeSupl>'
            . '</NFe>',
        );

        XmlHelper::ensureSignatureIsLast($dom);
        $xml = $dom->saveXML($dom->documentElement) ?: '';

        $this->assertMatchesRegularExpression(
            '/<infNFeSupl>.*<\/infNFeSupl><Signature/',
            $xml,
        );
    }
}
