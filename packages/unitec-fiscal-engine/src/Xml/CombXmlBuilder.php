<?php

namespace Unitec\FiscalEngine\Xml;

use DOMElement;
use Unitec\FiscalEngine\Dto\CombustivelDto;
use Unitec\FiscalEngine\Util\NumberFormatter;
use Unitec\FiscalEngine\Util\XmlHelper;

final class CombXmlBuilder
{
    public static function append(DOMElement $prod, CombustivelDto $comb): void
    {
        $node = $prod->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'comb');
        $prod->appendChild($node);

        XmlHelper::append($node, 'cProdANP', NumberFormatter::onlyDigits($comb->cProdANP));
        XmlHelper::append($node, 'descANP', XmlHelper::sanitizeText($comb->descANP, 95));

        if ($comb->pGLP !== null) {
            XmlHelper::append($node, 'pGLP', NumberFormatter::decimal($comb->pGLP, 4));
        }

        if ($comb->pGNn !== null) {
            XmlHelper::append($node, 'pGNn', NumberFormatter::decimal($comb->pGNn, 4));
        }

        if ($comb->pGNi !== null) {
            XmlHelper::append($node, 'pGNi', NumberFormatter::decimal($comb->pGNi, 4));
        }

        XmlHelper::append($node, 'UFCons', strtoupper(trim($comb->ufCons)));
    }
}
