<?php



namespace Unitec\FiscalEngine\Nfce;



use DateTimeImmutable;

use Unitec\FiscalEngine\Certificate\Certificate;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

use Unitec\FiscalEngine\Util\NumberFormatter;



final class NfceQrCodeBuilder

{

    public function buildUrl(

        string $chave,

        int $tpAmb,

        int $tpEmis,

        int $versaoQrcode,

        string $idToken,

        string $csc,

        string $dhEmiIso,

        float $valorNota,

        string $digestValueBase64,

        ?Certificate $certificate = null,

        ?string $cpf = null,

        ?string $cnpj = null,

    ): string {

        $urlBase = $this->normalizeConsultaUrl(ScNfceEndpoints::consultaQrCode($tpAmb));

        $versao = max(2, $versaoQrcode);



        if ($versao >= 3) {

            return $this->buildUrlVersao3(

                chave: $chave,

                urlBase: $urlBase,

                tpAmb: $tpAmb,

                tpEmis: $tpEmis,

                dhEmiIso: $dhEmiIso,

                valorNota: $valorNota,

                certificate: $certificate,

                cpf: $cpf,

                cnpj: $cnpj,

            );

        }



        return $this->buildUrlVersao2(

            chave: $chave,

            urlBase: $urlBase,

            tpAmb: $tpAmb,

            tpEmis: $tpEmis,

            idToken: $idToken,

            csc: $csc,

            dhEmiIso: $dhEmiIso,

            valorNota: $valorNota,

            digestValueBase64: $digestValueBase64,

        );

    }



    private function buildUrlVersao2(

        string $chave,

        string $urlBase,

        int $tpAmb,

        int $tpEmis,

        string $idToken,

        string $csc,

        string $dhEmiIso,

        float $valorNota,

        string $digestValueBase64,

    ): string {

        $versao = '2';

        $cscId = (string) ((int) $idToken);



        if ($tpEmis !== 9) {

            $seq = $chave . '|' . $versao . '|' . $tpAmb . '|' . $cscId;

            $hash = strtoupper(sha1($seq . $csc));



            return $urlBase . $seq . '|' . $hash;

        }



        $dia = (new DateTimeImmutable($dhEmiIso))->format('d');

        $valor = NumberFormatter::decimal($valorNota);

        $digHex = $this->digestBase64ToHex($digestValueBase64);

        $seq = $chave . '|' . $versao . '|' . $tpAmb . '|' . $dia . '|' . $valor . '|' . $digHex . '|' . $cscId;

        $hash = strtoupper(sha1($seq . $csc));



        return $urlBase . $seq . '|' . $hash;

    }



    private function buildUrlVersao3(

        string $chave,

        string $urlBase,

        int $tpAmb,

        int $tpEmis,

        string $dhEmiIso,

        float $valorNota,

        ?Certificate $certificate,

        ?string $cpf,

        ?string $cnpj,

    ): string {

        if ($tpEmis !== 9) {

            return $urlBase . $chave . '|3|' . $tpAmb;

        }



        if ($certificate === null) {

            throw new FiscalEngineException('Certificado obrigatório para QR Code versão 3 em contingência.');

        }



        $dia = (new DateTimeImmutable($dhEmiIso))->format('d');

        $valor = NumberFormatter::decimal($valorNota);

        [$tpIdDest, $cDest] = $this->resolveDestinatario($cpf, $cnpj);

        $payload = $chave . '|3|' . $tpAmb . '|' . $dia . '|' . $valor . '|' . $tpIdDest . '|' . $cDest;

        $assinatura = base64_encode($this->signPayload($certificate, $payload));



        return $urlBase . $payload . '|' . $assinatura;

    }



    /**

     * @return array{0: string, 1: string}

     */

    private function resolveDestinatario(?string $cpf, ?string $cnpj): array

    {

        $cpfDigits = NumberFormatter::onlyDigits($cpf);

        $cnpjDigits = NumberFormatter::onlyDigits($cnpj);



        if (strlen($cpfDigits) === 11) {

            return ['2', $cpfDigits];

        }



        if (strlen($cnpjDigits) === 14) {

            return ['1', $cnpjDigits];

        }



        return ['', ''];

    }



    private function normalizeConsultaUrl(string $url): string

    {

        $url = rtrim(trim($url), '/');



        if (str_contains($url, '?p=')) {

            return $url;

        }



        return $url . '?p=';

    }



    private function digestBase64ToHex(string $digestValueBase64): string

    {

        $hex = '';

        $length = strlen($digestValueBase64);



        for ($i = 0; $i < $length; $i++) {

            $hex .= sprintf('%02x', ord($digestValueBase64[$i]));

        }



        return $hex;

    }



    private function signPayload(Certificate $certificate, string $payload): string

    {

        $signature = '';

        $key = openssl_pkey_get_private($certificate->privateKeyPem);



        if ($key === false || ! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA1)) {

            throw new FiscalEngineException('Não foi possível assinar o QR Code da NFC-e.');

        }



        return $signature;

    }

}

