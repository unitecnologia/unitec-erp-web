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
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');

        return $value;
    }

    /**
     * Sanitiza infAdProd / infCpl conforme padrão SEFAZ (TString — cStat 225).
     *
     * Remove quebras de linha, caracteres de controle, símbolos XML problemáticos
     * e codepoints fora do intervalo aceito (espaço + U+0021..U+00FF).
     */
    public static function sanitizeInfAdProd(string $value, int $maxLength = 500): string
    {
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
