<?php

namespace Unitec\FiscalEngine\Xml;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;

/**
 * Checagem estrutural de infAdProd (cStat 225).
 *
 * Não substitui o XSD oficial da SEFAZ. Só impede infAdProd dentro de prod e
 * infAdProd fora da faixa opcional do det. Grupos opcionais (impostoDevol,
 * obsItem, vItem, DFeReferenciado) são permitidos na ordem do leiaute PL_010:
 *
 * prod → imposto → [impostoDevol] → [infAdProd] → [obsItem] → [vItem] → [DFeReferenciado]
 *
 * A validação é somente leitura: não altera o DOM/XML inspecionado.
 */
final class NfeXmlSchemaGuard
{
    public static function assertDetLayoutXml(string $xml): void
    {
        $copy = new DOMDocument('1.0', 'UTF-8');
        $copy->preserveWhiteSpace = true;
        $loaded = @$copy->loadXML($xml);

        if ($loaded !== true) {
            throw new FiscalEngineException('XML da NF-e inválido para validação de schema.');
        }

        self::assertDetLayout($copy);
    }

    public static function assertDetLayout(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfe', XmlHelper::NFE_NS);

        $dentroDeProd = $xpath->query('//nfe:det/nfe:prod/nfe:infAdProd');
        if ($dentroDeProd !== false && $dentroDeProd->length > 0) {
            throw new FiscalEngineException(
                'Falha no schema XML da NF-e: infAdProd deve ser filho de det, não de prod.',
            );
        }

        $dets = $xpath->query('//nfe:det');
        if ($dets === false) {
            return;
        }

        foreach ($dets as $det) {
            if ($det instanceof DOMElement) {
                self::assertInfAdProdNaFaixaDoDet($det);
            }
        }
    }

    /**
     * infAdProd, se existir, deve estar depois de prod/imposto/impostoDevol e
     * antes de obsItem/vItem/DFeReferenciado. Ausência de qualquer opcional é válida.
     */
    private static function assertInfAdProdNaFaixaDoDet(DOMElement $det): void
    {
        $names = [];

        foreach ($det->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $names[] = $child->localName;
            }
        }

        $idx = array_search('infAdProd', $names, true);
        if ($idx === false) {
            return;
        }

        $antes = array_slice($names, 0, $idx);
        $depois = array_slice($names, $idx + 1);

        foreach (['prod', 'imposto', 'impostoDevol'] as $deveEstarAntes) {
            if (in_array($deveEstarAntes, $depois, true)) {
                throw new FiscalEngineException(
                    'Falha no schema XML da NF-e: infAdProd fora da ordem em det (antes de '.$deveEstarAntes.').',
                );
            }
        }

        foreach (['obsItem', 'vItem', 'DFeReferenciado'] as $deveEstarDepois) {
            if (in_array($deveEstarDepois, $antes, true)) {
                throw new FiscalEngineException(
                    'Falha no schema XML da NF-e: infAdProd fora da ordem em det (depois de '.$deveEstarDepois.').',
                );
            }
        }
    }
}
