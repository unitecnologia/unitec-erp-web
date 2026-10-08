<?php

namespace App\Support\Fiscal;

use DOMDocument;
use DOMElement;

/**
 * Montagem segura do XML autorizado (nfeProc) a partir da NFe assinada gravada no ERP
 * e do protNFe devolvido pela SEFAZ (autorização ou consulta). Nunca grava o retorno
 * da consulta (retConsSitNFe) no lugar do documento fiscal.
 */
final class NfceXmlProtocolo
{
    private const NFE_NS = 'http://www.portalfiscal.inf.br/nfe';

    /** NFe assinada (sem declaração), extraída de NFe solta ou de nfeProc. */
    public static function nfe(?string $xml): ?string
    {
        if (! is_string($xml) || $xml === '') {
            return null;
        }

        return preg_match('/<NFe\b[\s\S]*?<\/NFe>/', $xml, $m) === 1 ? $m[0] : null;
    }

    public static function temProtocolo(?string $xml): bool
    {
        return is_string($xml) && str_contains($xml, '<protNFe');
    }

    public static function protNFe(?string $xml): ?string
    {
        if (! is_string($xml) || ! str_contains($xml, 'protNFe')) {
            return null;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            return preg_match('/<protNFe\b[\s\S]*?<\/protNFe>/', $xml, $m) === 1 ? $m[0] : null;
        }

        $prot = $dom->getElementsByTagName('protNFe')->item(0);

        return $prot instanceof DOMElement ? ($dom->saveXML($prot) ?: null) : null;
    }

    public static function chaveDoProtocolo(string $protNFe): string
    {
        return self::tag($protNFe, 'chNFe');
    }

    public static function protocoloDoProtocolo(string $protNFe): string
    {
        return self::tag($protNFe, 'nProt');
    }

    /** digVal do protocolo confere com o DigestValue da NFe assinada local? */
    public static function digestConfere(string $nfe, string $protNFe): bool
    {
        $digVal = self::tag($protNFe, 'digVal');
        $digest = self::tag($nfe, 'DigestValue');

        return $digVal !== '' && $digest !== '' && hash_equals($digest, $digVal);
    }

    public static function digestValue(string $nfe): string
    {
        return self::tag($nfe, 'DigestValue');
    }

    /** URL do QR Code gravada no infNFeSupl do XML (sem CDATA/entidades). */
    public static function qrCodeDoXml(string $nfe): string
    {
        if (preg_match('/<qrCode>(?:<!\[CDATA\[)?([\s\S]*?)(?:\]\]>)?<\/qrCode>/', $nfe, $m) !== 1) {
            return '';
        }

        return trim(html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    public static function tpEmis(?string $nfe): ?int
    {
        $valor = is_string($nfe) ? self::tag($nfe, 'tpEmis') : '';

        return $valor !== '' ? (int) $valor : null;
    }

    public static function montarNfeProc(string $nfe, string $protNFe): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<nfeProc xmlns="'.self::NFE_NS.'" versao="4.00">'
            .$nfe
            .$protNFe
            .'</nfeProc>';
    }

    /**
     * nProt do evento de cancelamento (110111) homologado, quando presente na consulta.
     */
    public static function protocoloCancelamento(?string $xml): string
    {
        if (! is_string($xml) || ! str_contains($xml, '110111')) {
            return '';
        }

        if (preg_match_all('/<retEvento\b[\s\S]*?<\/retEvento>/', $xml, $eventos) < 1) {
            return '';
        }

        foreach ($eventos[0] as $evento) {
            if (self::tag($evento, 'tpEvento') === '110111'
                && in_array(self::tag($evento, 'cStat'), ['135', '155'], true)) {
                return self::tag($evento, 'nProt');
            }
        }

        return '';
    }

    /** Primeira chave de 44 dígitos citada no texto (ex.: rejeição 539 "[chNFe: ...]"). */
    public static function chaveCitada(string $texto): ?string
    {
        return preg_match('/(?<!\d)(\d{44})(?!\d)/', $texto, $m) === 1 ? $m[1] : null;
    }

    /** Primeira chave citada que não é a da própria nota (chave conflitante da 539). */
    public static function chaveConflitante(string $texto, ?string $propria): ?string
    {
        preg_match_all('/(?<!\d)(\d{44})(?!\d)/', $texto, $m);

        foreach ($m[1] ?? [] as $chave) {
            if ($chave !== (string) $propria) {
                return $chave;
            }
        }

        return null;
    }

    private static function tag(string $xml, string $tag): string
    {
        return preg_match('/<(?:\w+:)?'.$tag.'\b[^>]*>([^<]*)<\/(?:\w+:)?'.$tag.'>/', $xml, $m) === 1
            ? trim($m[1])
            : '';
    }
}
