<?php

namespace App\Support\Erp\Nfse;

/**
 * Extração leve de campos do XML autorizado da NFS-e Nacional.
 */
final class NfseSefinXmlLeitor
{
    public static function numeroNfse(?string $xml): ?string
    {
        if ($xml === null || trim($xml) === '') {
            return null;
        }

        if (preg_match('/<(?:\w+:)?nNFSe>\s*([^<]+)\s*<\/(?:\w+:)?nNFSe>/i', $xml, $encontrado) !== 1) {
            return null;
        }

        $numero = trim($encontrado[1]);

        return $numero === '' ? null : $numero;
    }
}
