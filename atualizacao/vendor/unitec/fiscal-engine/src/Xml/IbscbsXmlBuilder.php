<?php

namespace Unitec\FiscalEngine\Xml;

use DOMElement;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Util\NumberFormatter;
use Unitec\FiscalEngine\Util\XmlHelper;

/**
 * Grupo UB (NT 2024.002 / 2025.002) — IBS / CBS no item e totais.
 */
final class IbscbsXmlBuilder
{
    public static function appendItem(DOMElement $imposto, ItemImpostoDto $itemImposto): void
    {
        if (! $itemImposto->hasIbscbs()) {
            return;
        }

        $cst = str_pad(NumberFormatter::onlyDigits((string) $itemImposto->cstIbsCbs), 3, '0', STR_PAD_LEFT);
        $cClass = str_pad(NumberFormatter::onlyDigits((string) $itemImposto->cClassTrib), 6, '0', STR_PAD_LEFT);

        $ibscbs = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'IBSCBS');
        $imposto->appendChild($ibscbs);

        XmlHelper::append($ibscbs, 'CST', $cst);
        XmlHelper::append($ibscbs, 'cClassTrib', $cClass);

        $g = $ibscbs->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gIBSCBS');
        $ibscbs->appendChild($g);

        XmlHelper::append($g, 'vBC', NumberFormatter::decimal($itemImposto->vBcIbscbs));

        self::appendAliquotaGrupo(
            parent: $g,
            groupName: 'gIBSUF',
            pTag: 'pIBSUF',
            vTag: 'vIBSUF',
            pAliq: $itemImposto->pIbsUf,
            vTrib: $itemImposto->vIbsUf,
            pRed: $itemImposto->pRedIbs,
        );

        self::appendAliquotaGrupo(
            parent: $g,
            groupName: 'gIBSMun',
            pTag: 'pIBSMun',
            vTag: 'vIBSMun',
            pAliq: $itemImposto->pIbsMun,
            vTrib: $itemImposto->vIbsMun,
            pRed: $itemImposto->pRedIbs,
        );

        XmlHelper::append($g, 'vIBS', NumberFormatter::decimal($itemImposto->vIbs()));

        self::appendAliquotaGrupo(
            parent: $g,
            groupName: 'gCBS',
            pTag: 'pCBS',
            vTag: 'vCBS',
            pAliq: $itemImposto->pCbs,
            vTrib: $itemImposto->vCbs,
            pRed: $itemImposto->pRedCbs,
        );
    }

    /**
     * @param  list<ItemDto>  $itens
     */
    public static function appendTotais(DOMElement $total, array $itens): void
    {
        $comIbscbs = array_values(array_filter(
            $itens,
            static fn (ItemDto $item): bool => $item->imposto->hasIbscbs(),
        ));

        if ($comIbscbs === []) {
            return;
        }

        $vBc = 0.0;
        $vIbsUf = 0.0;
        $vIbsMun = 0.0;
        $vCbs = 0.0;

        foreach ($comIbscbs as $item) {
            $imp = $item->imposto;
            $vBc += $imp->vBcIbscbs;
            $vIbsUf += $imp->vIbsUf;
            $vIbsMun += $imp->vIbsMun;
            $vCbs += $imp->vCbs;
        }

        $vBc = round($vBc, 2);
        $vIbsUf = round($vIbsUf, 2);
        $vIbsMun = round($vIbsMun, 2);
        $vCbs = round($vCbs, 2);
        $vIbs = round($vIbsUf + $vIbsMun, 2);

        $ibscbsTot = $total->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'IBSCBSTot');
        $total->appendChild($ibscbsTot);

        XmlHelper::append($ibscbsTot, 'vBCIBSCBS', NumberFormatter::decimal($vBc));

        $gIbs = $ibscbsTot->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gIBS');
        $ibscbsTot->appendChild($gIbs);

        $gIbsUf = $gIbs->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gIBSUF');
        $gIbs->appendChild($gIbsUf);
        // Ordem do XSD (TIBSCBSTot): vDif → vDevTrib → vIBSUF
        XmlHelper::append($gIbsUf, 'vDif', '0.00');
        XmlHelper::append($gIbsUf, 'vDevTrib', '0.00');
        XmlHelper::append($gIbsUf, 'vIBSUF', NumberFormatter::decimal($vIbsUf));

        $gIbsMun = $gIbs->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gIBSMun');
        $gIbs->appendChild($gIbsMun);
        XmlHelper::append($gIbsMun, 'vDif', '0.00');
        XmlHelper::append($gIbsMun, 'vDevTrib', '0.00');
        XmlHelper::append($gIbsMun, 'vIBSMun', NumberFormatter::decimal($vIbsMun));

        XmlHelper::append($gIbs, 'vIBS', NumberFormatter::decimal($vIbs));
        XmlHelper::append($gIbs, 'vCredPres', '0.00');
        XmlHelper::append($gIbs, 'vCredPresCondSus', '0.00');

        $gCbs = $ibscbsTot->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gCBS');
        $ibscbsTot->appendChild($gCbs);
        XmlHelper::append($gCbs, 'vDif', '0.00');
        XmlHelper::append($gCbs, 'vDevTrib', '0.00');
        XmlHelper::append($gCbs, 'vCBS', NumberFormatter::decimal($vCbs));
        XmlHelper::append($gCbs, 'vCredPres', '0.00');
        XmlHelper::append($gCbs, 'vCredPresCondSus', '0.00');
    }

    private static function appendAliquotaGrupo(
        DOMElement $parent,
        string $groupName,
        string $pTag,
        string $vTag,
        float $pAliq,
        float $vTrib,
        float $pRed,
    ): void {
        $group = $parent->ownerDocument->createElementNS(XmlHelper::NFE_NS, $groupName);
        $parent->appendChild($group);

        XmlHelper::append($group, $pTag, NumberFormatter::decimal($pAliq, 4));

        if ($pRed > 0) {
            $pEfet = round($pAliq * (1 - ($pRed / 100)), 4);
            $gRed = $group->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'gRed');
            $group->appendChild($gRed);
            XmlHelper::append($gRed, 'pRedAliq', NumberFormatter::decimal($pRed, 4));
            XmlHelper::append($gRed, 'pAliqEfet', NumberFormatter::decimal($pEfet, 4));
        }

        XmlHelper::append($group, $vTag, NumberFormatter::decimal($vTrib));
    }
}
