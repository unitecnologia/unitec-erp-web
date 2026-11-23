<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Certificate\CnpjExtractor;

/**
 * Assina pedRegEvento/infPedReg com o mesmo perfil XMLDSig da DPS Nacional.
 */
class NfseSefinEventoAssinador
{
    private const DSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    public function __construct(
        private readonly NfseDpsAssinador $dpsAssinador = new NfseDpsAssinador,
    ) {}

    public function assinarCancelamento(Nfse $nfse, string $xml): string
    {
        return $this->assinar($xml, $this->dpsAssinador->certificadoDaNota($nfse));
    }

    public function assinar(string $xml, Certificate $certificate): string
    {
        $doc = $this->carregar($xml);
        $inf = $doc->getElementsByTagName('infPedReg')->item(0);
        $pedido = $inf?->parentNode;

        if (! $inf instanceof DOMElement || ! $pedido instanceof DOMElement || $pedido->localName !== 'pedRegEvento') {
            throw new NfseNaoTransmitida('Pedido de registro de evento inválido para assinatura.');
        }

        $id = trim($inf->getAttribute('Id'));

        if ($id === '' || ! str_starts_with($id, 'PRE')) {
            throw new NfseNaoTransmitida('Atributo Id de infPedReg ausente para a assinatura.');
        }

        $this->confirmarCnpjAutor($inf, $certificate->certificatePem);

        $antes = $inf->C14N(false, false);
        $digest = base64_encode(hash('sha1', $antes, true));

        $signature = $doc->createElementNS(self::DSIG_NS, 'Signature');
        $pedido->appendChild($signature);

        $signedInfo = $doc->createElementNS(self::DSIG_NS, 'SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalization = $doc->createElementNS(self::DSIG_NS, 'CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', self::C14N);
        $signedInfo->appendChild($canonicalization);

        $signatureMethod = $doc->createElementNS(self::DSIG_NS, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', self::DSIG_NS.'rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        $reference = $doc->createElementNS(self::DSIG_NS, 'Reference');
        $reference->setAttribute('URI', '#'.$id);
        $signedInfo->appendChild($reference);

        $transforms = $doc->createElementNS(self::DSIG_NS, 'Transforms');
        $reference->appendChild($transforms);

        foreach ([self::DSIG_NS.'enveloped-signature', self::C14N] as $algoritmo) {
            $transform = $doc->createElementNS(self::DSIG_NS, 'Transform');
            $transform->setAttribute('Algorithm', $algoritmo);
            $transforms->appendChild($transform);
        }

        $digestMethod = $doc->createElementNS(self::DSIG_NS, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DSIG_NS.'sha1');
        $reference->appendChild($digestMethod);
        $reference->appendChild($doc->createElementNS(self::DSIG_NS, 'DigestValue', $digest));

        $assinatura = '';
        $chave = openssl_pkey_get_private($certificate->privateKeyPem);

        if ($chave === false || ! openssl_sign($signedInfo->C14N(false, false), $assinatura, $chave, OPENSSL_ALGO_SHA1)) {
            throw new NfseNaoTransmitida('Não foi possível assinar o pedido de cancelamento com o certificado da empresa.');
        }

        $signature->appendChild($doc->createElementNS(self::DSIG_NS, 'SignatureValue', base64_encode($assinatura)));

        $keyInfo = $doc->createElementNS(self::DSIG_NS, 'KeyInfo');
        $signature->appendChild($keyInfo);
        $x509 = $doc->createElementNS(self::DSIG_NS, 'X509Data');
        $keyInfo->appendChild($x509);
        $x509->appendChild($doc->createElementNS(
            self::DSIG_NS,
            'X509Certificate',
            $this->certificadoDer($certificate->certificatePem),
        ));

        if ($inf->C14N(false, false) !== $antes) {
            throw new NfseNaoTransmitida('O XML fiscal de infPedReg foi alterado antes da assinatura.');
        }

        return $doc->saveXML() ?: '';
    }

    private function confirmarCnpjAutor(DOMElement $inf, string $certificadoPem): void
    {
        $autor = '';

        foreach ($inf->getElementsByTagName('CNPJAutor') as $no) {
            $autor = preg_replace('/\D/', '', $no->textContent) ?? '';
            break;
        }

        $titular = $this->cnpjTitular($certificadoPem);

        if ($autor === '' || $titular === '' || $autor !== $titular) {
            throw new NfseNaoTransmitida('O certificado da assinatura não pertence ao autor do cancelamento.');
        }
    }

    private function cnpjTitular(string $pem): string
    {
        $parsed = openssl_x509_parse($pem);

        if ($parsed === false) {
            return '';
        }

        $cn = (string) ($parsed['subject']['CN'] ?? '');

        if (preg_match('/:(\d{14})$/', $cn, $titular) === 1) {
            return $titular[1];
        }

        return CnpjExtractor::fromCertificatePem($pem, $parsed);
    }

    private function carregar(string $xml): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            throw new NfseNaoTransmitida('XML do pedido de evento inválido para assinatura.');
        }

        return $doc;
    }

    private function certificadoDer(string $pem): string
    {
        return trim((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem));
    }
}
