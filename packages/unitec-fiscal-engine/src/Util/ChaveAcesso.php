<?php

namespace Unitec\FiscalEngine\Util;

final class ChaveAcesso
{
    /** @var array<string, string> */
    private const UF_IBGE = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16', 'TO' => '17',
        'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27',
        'SE' => '28', 'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35', 'PR' => '41',
        'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51', 'GO' => '52', 'DF' => '53',
    ];

    public static function gerar(
        string $uf,
        \DateTimeInterface $emissao,
        string $cnpj,
        string $modelo,
        int $serie,
        int $numero,
        int $tpEmis,
        int $cNf,
    ): string {
        $cUf = self::UF_IBGE[strtoupper($uf)] ?? '42';
        $aamm = $emissao->format('ym');
        $cnpjDigits = str_pad(substr(preg_replace('/\D/', '', $cnpj) ?: '0', 0, 14), 14, '0', STR_PAD_LEFT);
        $serieFmt = str_pad((string) $serie, 3, '0', STR_PAD_LEFT);
        $numeroFmt = str_pad((string) $numero, 9, '0', STR_PAD_LEFT);
        $cNfFmt = str_pad((string) ($cNf % 100000000), 8, '0', STR_PAD_LEFT);
        $base43 = $cUf . $aamm . $cnpjDigits . $modelo . $serieFmt . $numeroFmt . (string) $tpEmis . $cNfFmt;

        return $base43 . (string) self::digitoVerificador($base43);
    }

    public static function digitoVerificador(string $chave43): int
    {
        $multiplicadores = [2, 3, 4, 5, 6, 7, 8, 9];
        $soma = 0;
        $pos = 0;

        for ($i = strlen($chave43) - 1; $i >= 0; $i--) {
            $soma += (int) $chave43[$i] * $multiplicadores[$pos % 8];
            $pos++;
        }

        $resto = $soma % 11;

        return ($resto === 0 || $resto === 1) ? 0 : 11 - $resto;
    }

    public static function randomCNf(): int
    {
        return random_int(1, 99999999);
    }

    public static function cUfFromSigla(string $uf): string
    {
        return self::UF_IBGE[strtoupper($uf)] ?? '42';
    }
}
