<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Nfse;
use App\Models\VendasParametro;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use DOMDocument;
use DOMElement;
use Throwable;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Certificate\CnpjExtractor;

/**
 * XMLDSig do RPS IPM (NTE 123/2025, item 5.5): C14N 20010315, RSA-SHA1, SHA1, enveloped-signature,
 * Reference URI = #Id de InfDeclaracaoPrestacaoServico e Signature como último filho de Rps.
 */
class NfseIpmAssinador
{
    private const DSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    public function assinarNota(Nfse $nfse, string $xml): string
    {
        return $this->assinar($xml, $this->certificadoDaNota($nfse));
    }

    public function assinar(string $xml, Certificate $certificate): string
    {
        $doc = $this->carregar($xml);
        $inf = $this->infDeclaracao($doc);
        $rps = $inf->parentNode;

        if (! $rps instanceof DOMElement || $rps->localName !== 'Rps') {
            throw new NfseNaoTransmitida('Elemento Rps não encontrado para a assinatura do RPS IPM.');
        }

        $id = trim($inf->getAttribute('Id'));

        if ($id === '') {
            throw new NfseNaoTransmitida('Atributo Id de InfDeclaracaoPrestacaoServico ausente para a assinatura.');
        }

        $this->confirmarEmitente($doc, $certificate->certificatePem);

        $digest = base64_encode(hash('sha1', $inf->C14N(false, false), true));

        $signature = $doc->createElementNS(self::DSIG_NS, 'Signature');
        $rps->appendChild($signature);

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
            throw new NfseNaoTransmitida('Não foi possível assinar o RPS com o certificado da empresa.');
        }

        $signature->appendChild($doc->createElementNS(self::DSIG_NS, 'SignatureValue', base64_encode($assinatura)));

        $keyInfo = $doc->createElementNS(self::DSIG_NS, 'KeyInfo');
        $signature->appendChild($keyInfo);
        $x509 = $doc->createElementNS(self::DSIG_NS, 'X509Data');
        $keyInfo->appendChild($x509);
        $x509->appendChild($doc->createElementNS(self::DSIG_NS, 'X509Certificate', $this->certificadoDer($certificate->certificatePem)));

        $xmlAssinado = $doc->saveXML() ?: '';
        $this->validar($xmlAssinado);

        return $xmlAssinado;
    }

    public function validar(string $xml): void
    {
        $doc = $this->carregar($xml);
        $inf = $this->infDeclaracao($doc);
        $signature = $doc->getElementsByTagNameNS(self::DSIG_NS, 'Signature')->item(0);

        if (! $signature instanceof DOMElement || $signature->parentNode !== $inf->parentNode) {
            throw new NfseNaoTransmitida('A assinatura do RPS IPM deve ficar dentro de Rps.');
        }

        $reference = $signature->getElementsByTagNameNS(self::DSIG_NS, 'Reference')->item(0);

        if (! $reference instanceof DOMElement || $reference->getAttribute('URI') !== '#'.$inf->getAttribute('Id')) {
            throw new NfseNaoTransmitida('A referência da assinatura não aponta para o Id do RPS.');
        }

        $digest = trim($signature->getElementsByTagNameNS(self::DSIG_NS, 'DigestValue')->item(0)?->textContent ?? '');

        if ($digest !== base64_encode(hash('sha1', $inf->C14N(false, false), true))) {
            throw new NfseNaoTransmitida('O digest da assinatura não confere com o RPS.');
        }

        $signedInfo = $signature->getElementsByTagNameNS(self::DSIG_NS, 'SignedInfo')->item(0);
        $valor = $signature->getElementsByTagNameNS(self::DSIG_NS, 'SignatureValue')->item(0)?->textContent ?? '';
        $certificado = $signature->getElementsByTagNameNS(self::DSIG_NS, 'X509Certificate')->item(0)?->textContent ?? '';
        $chave = openssl_pkey_get_public($this->pemDoDer($certificado));

        if (! $signedInfo instanceof DOMElement || $chave === false || openssl_verify(
            $signedInfo->C14N(false, false),
            base64_decode(preg_replace('/\s+/', '', $valor) ?? '', true) ?: '',
            $chave,
            OPENSSL_ALGO_SHA1,
        ) !== 1) {
            throw new NfseNaoTransmitida('Assinatura digital do RPS inválida.');
        }
    }

    public function certificadoDaNota(Nfse $nfse): Certificate
    {
        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;
        $parametros = $empresa !== null
            ? VendasParametro::query()->where('empresa_id', $empresa->id)->first()
            : null;

        if ($empresa === null || ! $parametros instanceof VendasParametro) {
            throw new NfseNaoTransmitida('Configure o certificado digital A1 da empresa para assinar o RPS.');
        }

        try {
            return NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        } catch (Throwable) {
            throw new NfseNaoTransmitida('Não foi possível carregar o certificado digital A1 da empresa para assinar o RPS.');
        }
    }

    private function confirmarEmitente(DOMDocument $doc, string $certificadoPem): void
    {
        $prestador = $doc->getElementsByTagName('Prestador')->item(0);
        $cnpj = $prestador instanceof DOMElement
            ? (preg_replace('/\D/', '', $prestador->getElementsByTagName('Cnpj')->item(0)?->textContent ?? '') ?? '')
            : '';
        $titular = $this->cnpjTitular($certificadoPem);

        if ($cnpj === '' || $titular === '' || substr($cnpj, 0, 8) !== substr($titular, 0, 8)) {
            throw new NfseNaoTransmitida('O certificado digital não pertence ao CNPJ do prestador.');
        }
    }

    private function cnpjTitular(string $pem): string
    {
        $parsed = openssl_x509_parse($pem);

        if ($parsed === false) {
            return '';
        }

        if (preg_match('/:(\d{14})$/', (string) ($parsed['subject']['CN'] ?? ''), $titular) === 1) {
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
            throw new NfseNaoTransmitida('XML do RPS inválido para assinatura.');
        }

        return $doc;
    }

    private function infDeclaracao(DOMDocument $doc): DOMElement
    {
        $inf = $doc->getElementsByTagName('InfDeclaracaoPrestacaoServico')->item(0);

        if (! $inf instanceof DOMElement) {
            throw new NfseNaoTransmitida('Elemento InfDeclaracaoPrestacaoServico não encontrado para assinatura.');
        }

        return $inf;
    }

    private function certificadoDer(string $pem): string
    {
        return trim((string) preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem));
    }

    private function pemDoDer(string $der): string
    {
        $limpo = preg_replace('/\s+/', '', $der) ?? '';

        return "-----BEGIN CERTIFICATE-----\n".trim(chunk_split($limpo, 64, "\n"))."\n-----END CERTIFICATE-----\n";
    }
}
