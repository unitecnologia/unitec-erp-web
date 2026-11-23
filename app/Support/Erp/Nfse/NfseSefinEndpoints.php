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
        return self::baseNfse($url) !== null;
    }

    public static function nfse(NfseSefinAmbiente $ambiente): string
    {
        return $ambiente->url();
    }

    public static function eventos(NfseSefinAmbiente $ambiente, string $chaveAcesso): string
    {
        return rtrim($ambiente->url(), '/').'/'.self::chave($chaveAcesso).'/eventos';
    }

    public static function evento(
        NfseSefinAmbiente $ambiente,
        string $chaveAcesso,
        string $tipoEvento,
        string $numSeqEvento,
    ): string {
        $tipo = preg_replace('/\D/', '', $tipoEvento) ?? '';
        $seq = preg_replace('/\D/', '', $numSeqEvento) ?? '';

        if ($tipo === '' || $seq === '') {
            throw new NfseNaoTransmitida('Tipo ou sequencial do evento SEFIN inválido.');
        }

        return self::eventos($ambiente, $chaveAcesso).'/'.$tipo.'/'.$seq;
    }

    /**
     * Aceita a URL base de emissão e subcaminhos oficiais (/eventos, consulta etc.).
     */
    public static function baseNfse(string $url): ?string
    {
        foreach ([self::NFSE_PRODUCAO, self::NFSE_PRODUCAO_RESTRITA] as $base) {
            if ($url === $base || str_starts_with($url, $base.'/')) {
                return $base;
            }
        }

        return null;
    }

    private static function chave(string $chaveAcesso): string
    {
        $chave = preg_replace('/\D/', '', $chaveAcesso) ?? '';

        if (strlen($chave) !== 50) {
            throw new NfseNaoTransmitida('Chave de acesso da NFS-e Nacional inválida.');
        }

        return $chave;
    }
}
