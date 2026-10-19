<?php

namespace Unitec\FiscalEngine\Nfce;

/**
 * Roteia endpoints SOAP da NFC-e por UF (SC → SVRS/SAT; PR → SEFAZ-PR).
 */
final class NfceSoapUrls
{
    public static function autorizacao(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::autorizacao($tpAmb),
            default => ScNfceEndpoints::autorizacao($tpAmb),
        };
    }

    public static function consultaProtocolo(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::consultaProtocolo($tpAmb),
            default => ScNfceEndpoints::consultaProtocolo($tpAmb),
        };
    }

    public static function recepcaoEvento(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::recepcaoEvento($tpAmb),
            default => ScNfceEndpoints::recepcaoEvento($tpAmb),
        };
    }

    public static function inutilizacao(string $chaveOuUf, int $tpAmb): string
    {
        return match (NfcePublicUrls::assertSupportedUf($chaveOuUf)) {
            'PR' => PrNfceEndpoints::inutilizacao($tpAmb),
            default => ScNfceEndpoints::inutilizacao($tpAmb),
        };
    }
}
