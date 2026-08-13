<?php



namespace Unitec\FiscalEngine\Xml;



use DOMDocument;

use DOMElement;

use Unitec\FiscalEngine\Certificate\Certificate;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

use Unitec\FiscalEngine\Util\XmlHelper;



final class XmlSigner

{

    private const DSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';



    private const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';



    public function signInfNFe(DOMDocument $dom, Certificate $certificate): void
    {
        $this->signInfElement($dom, 'infNFe', $certificate, 'NFe');
    }

    public function signInfEvento(DOMDocument $dom, Certificate $certificate): void
    {
        $this->signInfElement($dom, 'infEvento', $certificate, 'evento');
    }

    public function signInfInut(DOMDocument $dom, Certificate $certificate): void
    {
        $this->signInfElement($dom, 'infInut', $certificate, 'inutNFe');
    }

    private function signInfElement(
        DOMDocument $dom,
        string $infTag,
        Certificate $certificate,
        string $parentTag,
    ): void {
        $infNode = $dom->getElementsByTagName($infTag)->item(0);

        if (! $infNode instanceof DOMElement) {
            throw new FiscalEngineException("Elemento {$infTag} não encontrado para assinatura.");
        }

        $parent = $infNode->parentNode;

        if (! $parent instanceof DOMElement || $parent->localName !== $parentTag) {
            throw new FiscalEngineException("Elemento {$parentTag} não encontrado para assinatura.");
        }

        XmlHelper::removeWhitespaceTextNodes($infNode);

        $id = trim($infNode->getAttribute('Id'));

        if ($id === '') {
            throw new FiscalEngineException("Atributo Id do {$infTag} não informado para assinatura.");
        }

        $digestValue = base64_encode(hash('sha1', $infNode->C14N(true, false), true));

        $signature = $dom->createElementNS(self::DSIG_NS, 'Signature');
        $parent->appendChild($signature);

        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalization = $dom->createElement('CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', self::C14N);
        $signedInfo->appendChild($canonicalization);

        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::DSIG_NS . 'rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $id);
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $transformEnvelope = $dom->createElement('Transform');
        $transformEnvelope->setAttribute('Algorithm', self::DSIG_NS . 'enveloped-signature');
        $transforms->appendChild($transformEnvelope);

        $transformC14n = $dom->createElement('Transform');
        $transformC14n->setAttribute('Algorithm', self::C14N);
        $transforms->appendChild($transformC14n);

        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DSIG_NS . 'sha1');
        $reference->appendChild($digestMethod);
        $reference->appendChild($dom->createElement('DigestValue', $digestValue));

        $signedInfoCanonical = $signedInfo->C14N(true, false);
        $signatureBinary = $this->signPayload($certificate, $signedInfoCanonical);
        $signature->appendChild($dom->createElement('SignatureValue', base64_encode($signatureBinary)));

        $keyInfo = $dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);
        $x509Data->appendChild($dom->createElement('X509Certificate', $this->certificateBase64Der($certificate->certificatePem)));

        $this->assertSignedInfElementIsValid($dom, $infTag, $certificate);
    }



    public function extractDigestValue(DOMDocument $dom): string

    {

        $nodes = $dom->getElementsByTagName('DigestValue');



        if ($nodes->length === 0) {

            return '';

        }



        return (string) $nodes->item(0)?->textContent;

    }



    public function assertSignatureIsValid(DOMDocument $dom, Certificate $certificate): void
    {
        $this->assertSignedInfElementIsValid($dom, 'infNFe', $certificate);
    }

    private function assertSignedInfElementIsValid(DOMDocument $dom, string $infTag, Certificate $certificate): void
    {
        $signedInfo = $dom->getElementsByTagName('SignedInfo')->item(0);
        $signatureValue = $dom->getElementsByTagName('SignatureValue')->item(0);
        $infNode = $dom->getElementsByTagName($infTag)->item(0);
        $digestNode = $dom->getElementsByTagName('DigestValue')->item(0);

        if (! $signedInfo instanceof DOMElement || ! $signatureValue instanceof DOMElement || ! $infNode instanceof DOMElement || ! $digestNode instanceof DOMElement) {
            throw new FiscalEngineException('Estrutura de assinatura incompleta.');
        }

        $expectedDigest = base64_encode(hash('sha1', $infNode->C14N(true, false), true));

        if ((string) $digestNode->textContent !== $expectedDigest) {
            throw new FiscalEngineException("Digest do {$infTag} difere do elemento assinado.");
        }

        $publicKey = openssl_pkey_get_public($certificate->certificatePem);

        if ($publicKey === false) {
            throw new FiscalEngineException('Não foi possível ler a chave pública do certificado.');
        }

        $verified = openssl_verify(
            $signedInfo->C14N(true, false),
            base64_decode(str_replace(["\r", "\n"], '', (string) $signatureValue->textContent), true) ?: '',
            $publicKey,
            OPENSSL_ALGO_SHA1,
        );

        if ($verified !== 1) {
            throw new FiscalEngineException('Assinatura digital inválida antes do envio à SEFAZ.');
        }
    }



    private function signPayload(Certificate $certificate, string $payload): string

    {

        $signature = '';

        $key = openssl_pkey_get_private($certificate->privateKeyPem);



        if ($key === false || ! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA1)) {

            throw new FiscalEngineException('Não foi possível assinar o XML da NFC-e.');

        }



        return $signature;

    }



    private function certificateBase64Der(string $certificatePem): string

    {

        return trim((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $certificatePem));

    }

}

