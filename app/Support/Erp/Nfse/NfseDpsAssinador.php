<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use App\Models\VendasParametro;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Certificate\CnpjExtractor;

class NfseDpsAssinador
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
        $inf = $this->infDps($doc);
        $dps = $inf->parentNode;

        if (! $dps instanceof DOMElement || $dps->localName !== 'DPS') {
            throw new NfseDpsNaoAssinada('Elemento DPS não encontrado para assinatura.');
        }

        $id = trim($inf->getAttribute('Id'));

        if ($id === '' || ! str_starts_with($id, 'DPS')) {
            throw new NfseDpsNaoAssinada('Atributo Id de infDPS ausente para a assinatura.');
        }

        $antes = $inf->C14N(false, false);
        $digest = base64_encode(hash('sha1', $antes, true));

        $signature = $doc->createElementNS(self::DSIG_NS, 'Signature');
        $dps->appendChild($signature);

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

        $enveloped = $doc->createElementNS(self::DSIG_NS, 'Transform');
        $enveloped->setAttribute('Algorithm', self::DSIG_NS.'enveloped-signature');
        $transforms->appendChild($enveloped);

        $c14n = $doc->createElementNS(self::DSIG_NS, 'Transform');
        $c14n->setAttribute('Algorithm', self::C14N);
        $transforms->appendChild($c14n);

        $digestMethod = $doc->createElementNS(self::DSIG_NS, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DSIG_NS.'sha1');
        $reference->appendChild($digestMethod);
        $reference->appendChild($doc->createElementNS(self::DSIG_NS, 'DigestValue', $digest));

        $assinatura = $this->assinarBytes($certificate, $signedInfo->C14N(false, false));
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
            throw new NfseDpsNaoAssinada('O XML fiscal de infDPS foi alterado antes da assinatura.');
        }

        $xmlAssinado = $doc->saveXML() ?: '';
        $this->validar($xmlAssinado, $certificate);

        return $xmlAssinado;
    }

    public function validar(string $xml, Certificate $certificate): void
    {
        $doc = $this->carregar($xml);
        $inf = $this->infDps($doc);
        $id = trim($inf->getAttribute('Id'));
        $signedInfo = $this->primeiro($doc, 'SignedInfo');
        $signatureValue = $this->primeiro($doc, 'SignatureValue');
        $digest = $this->primeiro($doc, 'DigestValue');
        $reference = $this->primeiro($doc, 'Reference');
        $certificados = $doc->getElementsByTagNameNS(self::DSIG_NS, 'X509Certificate');

        if ($certificados->length !== 1) {
            throw new NfseDpsNaoAssinada('A assinatura deve conter somente o certificado final.');
        }

        if ($doc->getElementsByTagNameNS(self::DSIG_NS, 'X509IssuerSerial')->length > 0) {
            throw new NfseDpsNaoAssinada('A assinatura não pode incluir a cadeia do certificado.');
        }

        if (trim($reference->getAttribute('URI')) !== '#'.$id) {
            throw new NfseDpsNaoAssinada('A referência da assinatura não aponta para o Id de infDPS.');
        }

        $esperado = base64_encode(hash('sha1', $inf->C14N(false, false), true));

        if (trim($digest->textContent) !== $esperado) {
            throw new NfseDpsNaoAssinada('O digest da assinatura não confere com infDPS.');
        }

        $pem = $this->pemDoDer(trim($certificados->item(0)?->textContent ?? ''));
        $chave = openssl_pkey_get_public($pem);

        if ($chave === false) {
            throw new NfseDpsNaoAssinada('Não foi possível ler a chave pública da assinatura.');
        }

        $verificada = openssl_verify(
            $signedInfo->C14N(false, false),
            base64_decode(preg_replace('/\s+/', '', $signatureValue->textContent) ?? '', true) ?: '',
            $chave,
            OPENSSL_ALGO_SHA1,
        );

        if ($verificada !== 1) {
            throw new NfseDpsNaoAssinada('Assinatura digital da DPS inválida.');
        }

        if ($this->certificadoDer($pem) !== $this->certificadoDer($certificate->certificatePem)) {
            throw new NfseDpsNaoAssinada('O certificado da assinatura não é o certificado configurado da empresa.');
        }

        $this->confirmarEmitente($doc, $pem);
    }

    public function certificadoDaNota(Nfse $nfse): Certificate
    {
        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;

        if ($empresa === null) {
            throw new NfseDpsNaoAssinada('Empresa da NFS-e não encontrada para o certificado.');
        }

        $parametros = VendasParametro::query()->where('empresa_id', $empresa->id)->first();

        if (! $parametros instanceof VendasParametro) {
            throw new NfseDpsNaoAssinada('Parâmetros fiscais da empresa não encontrados.');
        }

        return NfceFiscalCertificateResolver::resolve($empresa, $parametros);
    }

    private function confirmarEmitente(DOMDocument $doc, string $certificadoPem): void
    {
        $prestador = $this->cnpjPrestador($doc);
        $doCertificado = $this->cnpjTitular($certificadoPem);

        if ($prestador === '' || $doCertificado === '' || $prestador !== $doCertificado) {
            throw new NfseDpsNaoAssinada('O certificado da assinatura não pertence ao emitente da DPS.');
        }
    }

    private function cnpjPrestador(DOMDocument $doc): string
    {
        $prest = $doc->getElementsByTagName('prest')->item(0);

        if (! $prest instanceof DOMElement) {
            return '';
        }

        foreach ($prest->getElementsByTagName('CNPJ') as $cnpj) {
            return preg_replace('/\D/', '', $cnpj->textContent) ?? '';
        }

        return '';
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
            throw new NfseDpsNaoAssinada('XML da DPS inválido para assinatura.');
        }

        return $doc;
    }

    private function infDps(DOMDocument $doc): DOMElement
    {
        $inf = $doc->getElementsByTagName('infDPS')->item(0);

        if (! $inf instanceof DOMElement) {
            throw new NfseDpsNaoAssinada('Elemento infDPS não encontrado para assinatura.');
        }

        return $inf;
    }

    private function primeiro(DOMDocument $doc, string $tag): DOMElement
    {
        $no = $doc->getElementsByTagNameNS(self::DSIG_NS, $tag)->item(0);

        if (! $no instanceof DOMElement) {
            throw new NfseDpsNaoAssinada('Estrutura de assinatura incompleta.');
        }

        return $no;
    }

    private function assinarBytes(Certificate $certificate, string $payload): string
    {
        $assinatura = '';
        $chave = openssl_pkey_get_private($certificate->privateKeyPem);

        if ($chave === false || ! openssl_sign($payload, $assinatura, $chave, OPENSSL_ALGO_SHA1)) {
            throw new NfseDpsNaoAssinada('Não foi possível assinar a DPS com o certificado da empresa.');
        }

        return $assinatura;
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
