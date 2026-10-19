<?php

namespace Unitec\FiscalEngine\Nfce;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Resolve URLs públicas de NFC-e (qrCode / urlChave) por UF e ambiente.
 * Somente SC e PR são suportados — sem fallback silencioso para SC.
 */
final class NfcePublicUrls
{
    /** @var array<string, string> */
    private const CUF_TO_UF = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA', '16' => 'AP', '17' => 'TO',
        '21' => 'MA', '22' => 'PI', '23' => 'CE', '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL',
        '28' => 'SE', '29' => 'BA', '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP', '41' => 'PR',
        '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
    ];

    /** @var list<string> */
    private const SUPPORTED = ['SC', 'PR'];

    public static function consultaQrCode(string $chaveOuUf, int $tpAmb): string
    {
        return match (self::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::consultaQrCode($tpAmb),
            default => ScNfceEndpoints::consultaQrCode($tpAmb),
        };
    }

    public static function urlChave(string $chaveOuUf, int $tpAmb): string
    {
        return match (self::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::urlChave($tpAmb),
            default => ScNfceEndpoints::consultaQrCode($tpAmb),
        };
    }

    /**
     * @return 'SC'|'PR'
     */
    public static function assertSupportedUf(string $chaveOuUf): string
    {
        $uf = self::resolveUf($chaveOuUf);

        if (! in_array($uf, self::SUPPORTED, true)) {
            throw new FiscalEngineException(
                'NFC-e: UF "'.($uf !== '' ? $uf : '?').'" não suportada. Suportadas: SC e PR.',
            );
        }

        return $uf;
    }

    public static function resolveUf(string $chaveOuUf): string
    {
        $value = strtoupper(trim($chaveOuUf));

        if (preg_match('/^[A-Z]{2}$/', $value) === 1) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) >= 2) {
            $cUf = substr($digits, 0, 2);

            return self::CUF_TO_UF[$cUf] ?? '';
        }

        return '';
    }
}
