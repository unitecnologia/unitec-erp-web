<?php

namespace Unitec\FiscalEngine\Util;

use DOMDocument;
use DOMElement;
use DOMNode;

final class XmlHelper
{
    public const NFE_NS = 'http://www.portalfiscal.inf.br/nfe';

    public static function createDocument(string $rootName, string $version = '4.00'): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $root = $dom->createElementNS(self::NFE_NS, $rootName);
        $root->setAttribute('versao', $version);
        $dom->appendChild($root);

        return $dom;
    }

    public static function append(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $element = $parent->ownerDocument->createElementNS(self::NFE_NS, $name);
        $element->appendChild($parent->ownerDocument->createTextNode($value));
        $parent->appendChild($element);
    }

    public static function sanitizeText(string $value, int $maxLength): string
    {
        // Repara mojibake SCV antes do corte — não grava no banco.
        $value = self::repairCommonMojibake($value);
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');

        return $value;
    }

    /**
     * TString SEFAZ (emit/xNome, xFant, xProd, endereços, infCpl, etc.) — cStat 215/225.
     *
     * Repara mojibake comum (Ő→Õ, Ă→Ã, Ę→Ê, ş→º) e remove codepoints fora de Latin-1
     * (U+0020 + U+0021..U+00FF). Não altera cadastro: só o valor enviado no XML.
     */
    public static function sanitizeTString(string $value, int $maxLength = 60): string
    {
        return self::sanitizeInfAdProd($value, $maxLength);
    }

    /**
     * Sanitiza infAdProd / infCpl / demais TString conforme padrão SEFAZ (cStat 225).
     *
     * Repara mojibake antes do filtro Latin-1 (senão Ő/Ă/Ę seriam descartados).
     * Remove quebras de linha, caracteres de controle, símbolos XML problemáticos
     * e codepoints fora do intervalo aceito (espaço + U+0021..U+00FF).
     * Preserva acentuação portuguesa já correta (ÃÕÊÇ etc.).
     */
    public static function sanitizeInfAdProd(string $value, int $maxLength = 500): string
    {
        $value = self::repairCommonMojibake($value);
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $value) ?? $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? $value;
        $value = str_replace(['<', '>', '&', '"', '\\'], '', $value);

        $filtered = '';

        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            $code = mb_ord($char, 'UTF-8');

            if ($code === 32 || ($code >= 33 && $code <= 255)) {
                $filtered .= $char;
            }
        }

        $value = trim(preg_replace('/\s+/', ' ', $filtered) ?? '');
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');

        if ($value === '') {
            return '';
        }

        $first = mb_ord(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
        $last = mb_ord(mb_substr($value, -1, 1, 'UTF-8'), 'UTF-8');

        if ($first < 33 || $last < 33) {
            return '';
        }

        return $value;
    }

    /**
     * Corrige bytes WIN1252 lidos como CP1250/ISO-8859-2 (migração SCV).
     * Só o valor em memória/XML — não grava no banco.
     *
     * Ă→Ã, ă→ã, Ő→Õ, ő→õ, Ę→Ê, ę→ê, ş/Ş→º.
     * Acentos portugueses válidos (ÃÕÊÇÁÉÍÓÚ etc.) permanecem intactos.
     */
    public static function repairCommonMojibake(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return strtr($value, [
            "\u{0150}" => "\u{00D5}", // Ő → Õ
            "\u{0151}" => "\u{00F5}", // ő → õ
            "\u{0102}" => "\u{00C3}", // Ă → Ã
            "\u{0103}" => "\u{00E3}", // ă → ã
            "\u{0118}" => "\u{00CA}", // Ę → Ê
            "\u{0119}" => "\u{00EA}", // ę → ê
            "\u{015F}" => "\u{00BA}", // ş → º (ex.: Nş. → Nº)
            "\u{015E}" => "\u{00BA}", // Ş → º
        ]);
    }

    public static function stripXmlDeclaration(string $xml): string
    {
        return trim((string) preg_replace('/<\?xml[^?]*\?>/', '', $xml));
    }

    /**
     * Remove espaços, tabs e quebras de linha entre tags (exigência SEFAZ — cStat 588).
     */
    public static function compact(string $xml): string
    {
        $xml = str_replace(["\r\n", "\r"], "\n", $xml);
        $xml = (string) preg_replace('/>\s+</', '><', $xml);

        return trim($xml);
    }

    public static function removeWhitespaceTextNodes(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        $toRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim((string) $child->textContent) === '') {
                $toRemove[] = $child;

                continue;
            }

            if ($child->hasChildNodes()) {
                self::removeWhitespaceTextNodes($child);
            }
        }

        foreach ($toRemove as $child) {
            $node->removeChild($child);
        }
    }

    public static function ensureSignatureIsLast(DOMDocument $dom): void
    {
        $nfe = $dom->getElementsByTagName('NFe')->item(0);
        $signature = $dom->getElementsByTagName('Signature')->item(0);

        if ($nfe instanceof DOMElement && $signature instanceof DOMElement) {
            $nfe->appendChild($signature);
        }
    }
}
