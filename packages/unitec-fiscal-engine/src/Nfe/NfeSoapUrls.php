<?php

namespace Unitec\FiscalEngine\Nfe;

use Unitec\FiscalEngine\Nfce\NfcePublicUrls;

/**
 * Roteia endpoints SOAP da NF-e (modelo 55) por UF (SC → SVRS; PR → SEFAZ-PR).
 */
final class NfeSoapUrls
{
    public static function autorizacao(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfeEndpoints::autorizacao($tpAmb),
            default => ScNfeEndpoints::autorizacao($tpAmb),
        };
    }

    public static function consultaProtocolo(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfeEndpoints::consultaProtocolo($tpAmb),
            default => ScNfeEndpoints::consultaProtocolo($tpAmb),
        };
    }

    public static function recepcaoEvento(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfeEndpoints::recepcaoEvento($tpAmb),
            default => ScNfeEndpoints::recepcaoEvento($tpAmb),
        };
    }

    public static function inutilizacao(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfeEndpoints::inutilizacao($tpAmb),
            default => ScNfeEndpoints::inutilizacao($tpAmb),
        };
    }
}
