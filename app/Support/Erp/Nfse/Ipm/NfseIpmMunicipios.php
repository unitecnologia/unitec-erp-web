<?php

namespace App\Support\Erp\Nfse\Ipm;

/**
 * Endpoints IPM informados oficialmente pelo suporte. Outros municípios IPM usam a URL digitada.
 */
final class NfseIpmMunicipios
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        '4101804' => 'https://araucaria.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe',
        '4203204' => 'https://camboriu.atende.net/?pg=services&service=WNENotaFiscalEletronicaNfe',
    ];

    public static function endpoint(mixed $codigoIbge): ?string
    {
        $codigo = preg_replace('/\D/', '', (string) $codigoIbge) ?? '';

        return self::ENDPOINTS[$codigo] ?? null;
    }
}
