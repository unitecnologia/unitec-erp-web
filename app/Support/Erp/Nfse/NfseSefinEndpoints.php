<?php

namespace App\Support\Erp\Nfse;

final class NfseSefinEndpoints
{
    public const HOST_PRODUCAO_RESTRITA = 'sefin.producaorestrita.nfse.gov.br';

    public const HOST_PRODUCAO = 'sefin.nfse.gov.br';

    public const NFSE_PRODUCAO = 'https://sefin.nfse.gov.br/SefinNacional/nfse';

    public const NFSE_PRODUCAO_RESTRITA = 'https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional/nfse';

    public static function urlOficial(string $url): bool
    {
        return $url === self::NFSE_PRODUCAO || $url === self::NFSE_PRODUCAO_RESTRITA;
    }

    public static function nfse(NfseSefinAmbiente $ambiente): string
    {
        return $ambiente->url();
    }
}
