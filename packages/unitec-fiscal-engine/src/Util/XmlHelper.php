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
