<?php

declare(strict_types=1);

namespace Unitec\FiscalEngine\Tests\Unit;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Util\XmlHelper;
use Unitec\FiscalEngine\Xml\XmlSigner;

final class XmlSignerTest extends TestCase
{
    public function test_assinatura_nao_inclui_x509_issuer_serial(): void
    {
        $certificate = $this->createSelfSignedCertificate();
        $dom = new DOMDocument('1.0', 'UTF-8');
use Unitec\FiscalEngine\Util\XmlHelper;

use Unitec\FiscalEngine\Xml\XmlSigner;



final class XmlSignerTest extends TestCase

{

    public function test_assinatura_valida_no_documento_e_apos_compact(): void

    {

        $certificate = $this->createSelfSignedCertificate();



        if ($certificate === null) {

            $this->markTestSkipped('OpenSSL indisponível para gerar certificado de teste.');

        }



        $dom = new DOMDocument('1.0', 'UTF-8');

        $dom->formatOutput = false;

        $dom->preserveWhiteSpace = false;

        $dom->loadXML(

            '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'

            . '<infNFe Id="NFe42260722469772000100650010000000011000000000" versao="4.00">'

            . '<ide><cUF>42</cUF></ide>'

            . '</infNFe>'

            . '</NFe>',

        );



        $signer = new XmlSigner();

        $signer->signInfNFe($dom, $certificate);

        $signer->assertSignatureIsValid($dom, $certificate);



        $compactXml = XmlHelper::compact($dom->saveXML($dom->documentElement) ?: '');

        $reloaded = new DOMDocument('1.0', 'UTF-8');

        $reloaded->preserveWhiteSpace = false;

        $reloaded->loadXML($compactXml);



        $signer->assertSignatureIsValid($reloaded, $certificate);

    }



    private function createSelfSignedCertificate(): ?Certificate

    {

        $config = [

            'digest_alg' => 'sha1',

            'private_key_bits' => 2048,

            'private_key_type' => OPENSSL_KEYTYPE_RSA,

        ];



        $opensslConf = getenv('OPENSSL_CONF');



        if ($opensslConf !== false && is_file($opensslConf)) {

            $config['config'] = $opensslConf;

        }



        $resource = openssl_pkey_new($config);



        if ($resource === false) {

            return null;

        }



        $csr = openssl_csr_new(['CN' => 'Unitec Test'], $resource, $config);



        if ($csr === false) {

            return null;

        }



        $x509 = openssl_csr_sign($csr, null, $resource, 1, $config);



        if ($x509 === false) {

            return null;

        }