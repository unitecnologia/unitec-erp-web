<?php

namespace Unitec\FiscalEngine\Xml;

use DOMElement;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Util\NumberFormatter;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfeImpostoXmlBuilder
{
    public static function appendIcms(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        $icms = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ICMS');
        $imposto->appendChild($icms);

        if ($imp->usesSimples()) {
            self::appendIcmsSimples($icms, $imp);

            return;
        }

        self::appendIcmsNormal($icms, $imp);
    }

    public static function appendIpi(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        if (! $imp->hasIpi()) {
            return;
        }

        $ipi = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'IPI');
        $imposto->appendChild($ipi);

        $cst = self::normalizeCst($imp->cstIpi ?? '99') ?: '99';
        $grupo = $ipi->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'IPITrib');
        $ipi->appendChild($grupo);

        XmlHelper::append($grupo, 'CST', $cst);
        XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcIpi));
        XmlHelper::append($grupo, 'pIPI', NumberFormatter::decimal($imp->pIpi, 2));
        XmlHelper::append($grupo, 'vIPI', NumberFormatter::decimal($imp->vIpi));
    }

    public static function appendPis(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        $pis = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PIS');
        $imposto->appendChild($pis);

        if ($imp->hasPisTributado()) {
            $grupo = $pis->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PISAliq');
            $pis->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $imp->cstPisResolvido());
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcPis));
            XmlHelper::append($grupo, 'pPIS', NumberFormatter::decimal($imp->pPis, 4));
            XmlHelper::append($grupo, 'vPIS', NumberFormatter::decimal($imp->vPis));

            return;
        }

        $cst = $imp->cstPisResolvido();
        if (! in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            $cst = '07';
        }

        $grupo = $pis->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PISNT');
        $pis->appendChild($grupo);
        XmlHelper::append($grupo, 'CST', $cst);
    }

    public static function appendCofins(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        $cofins = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINS');
        $imposto->appendChild($cofins);

        if ($imp->hasCofinsTributado()) {
            $grupo = $cofins->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINSAliq');
            $cofins->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $imp->cstCofinsResolvido());
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcCofins));
            XmlHelper::append($grupo, 'pCOFINS', NumberFormatter::decimal($imp->pCofins, 4));
            XmlHelper::append($grupo, 'vCOFINS', NumberFormatter::decimal($imp->vCofins));

            return;
        }

        $cst = $imp->cstCofinsResolvido();
        if (! in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            $cst = '07';
        }

        $grupo = $cofins->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINSNT');
        $cofins->appendChild($grupo);
        XmlHelper::append($grupo, 'CST', $cst);
    }

    /**
     * @return array{vBc: float, vIcms: float, vIcmsDeson: float, vIpi: float, vPis: float, vCofins: float}
     */
    public static function somarTotais(ItemImpostoDto $imp, array $acc): array
    {
        $icmsValores = self::resolveIcmsValores($imp);
        if ($icmsValores !== null) {
            $acc['vBc'] += $icmsValores['vBc'];
            $acc['vIcms'] += $icmsValores['vIcms'];
        }

        $acc['vIcmsDeson'] += $imp->vIcmsDeson;
        $acc['vIpi'] += $imp->vIpi;
        $acc['vPis'] += $imp->vPis;
        $acc['vCofins'] += $imp->vCofins;

        return $acc;
    }

    /**
     * Valores de ICMS que entram no XML do item e no ICMSTot (rejeição 531 se divergir).
     *
     * @return array{vBc: float, vIcms: float, pIcms: float}|null
     */
    public static function resolveIcmsValores(ItemImpostoDto $imp): ?array
    {
        if (! self::deveEmitirIcmsValores($imp)) {
            return null;
        }

        $vBc = round(max(0, $imp->vBc), 2);
        $pIcms = round(max(0, $imp->pIcms), 4);
        $vIcms = round(max(0, $imp->vIcms), 2);

        if ($vBc <= 0 && $pIcms <= 0 && $vIcms <= 0) {
            return null;
        }

        if ($vBc <= 0 && $vIcms > 0 && $pIcms > 0) {
            $vBc = round($vIcms / ($pIcms / 100), 2);
        } elseif ($vBc <= 0 && $vIcms > 0) {
            $vBc = $vIcms;
            $pIcms = 100.0;
        } elseif ($vBc > 0 && $vIcms <= 0 && $pIcms > 0) {
            $vIcms = round($vBc * $pIcms / 100, 2);
        } elseif ($vBc > 0 && $vIcms > 0 && $pIcms <= 0) {
            $pIcms = round($vIcms / $vBc * 100, 4);
        } elseif ($vBc > 0 && $vIcms <= 0 && $pIcms <= 0) {
            return null;
        }

        if ($vBc <= 0 || $vIcms <= 0 || $pIcms <= 0) {
            return null;
        }

        return [
            'vBc' => $vBc,
            'vIcms' => $vIcms,
            'pIcms' => $pIcms,
        ];
    }

    public static function deveEmitirIcmsValores(ItemImpostoDto $imp): bool
    {
        if ($imp->usesSimples()) {
            $csosn = $imp->csosnResolvido();

            return $csosn === '900'
                || ($imp->hasIcmsDetalhe() && ! in_array($csosn, ['102', '103', '300', '400', '500'], true));
        }

        return in_array($imp->cstIcmsResolvido(), ['00', '10', '20', '51', '70', '90'], true);
    }

    private static function appendIcmsSimples(DOMElement $icms, ItemImpostoDto $imp): void
    {
        $csosn = $imp->csosnResolvido();
        $tag = 'ICMSSN' . $csosn;
        $grupo = $icms->ownerDocument->createElementNS(XmlHelper::NFE_NS, $tag);
        $icms->appendChild($grupo);

        XmlHelper::append($grupo, 'orig', (string) $imp->origem);
        XmlHelper::append($grupo, 'CSOSN', $csosn);

        if ($csosn === '900' || ($imp->hasIcmsDetalhe() && ! in_array($csosn, ['102', '103', '300', '400', '500'], true))) {
            self::appendIcmsValores($grupo, $imp);
        }
    }

    private static function appendIcmsNormal(DOMElement $icms, ItemImpostoDto $imp): void
    {
        $cst = $imp->cstIcmsResolvido();
        $tag = 'ICMS' . $cst;
        $grupo = $icms->ownerDocument->createElementNS(XmlHelper::NFE_NS, $tag);
        $icms->appendChild($grupo);

        XmlHelper::append($grupo, 'orig', (string) $imp->origem);
        XmlHelper::append($grupo, 'CST', $cst);

        if (in_array($cst, ['00', '10', '20', '51', '70', '90'], true)) {
            self::appendIcmsValores($grupo, $imp);
        }

        if ($imp->vIcmsDeson > 0 && self::filled($imp->motivoDesoneracao)) {
            XmlHelper::append($grupo, 'vICMSDeson', NumberFormatter::decimal($imp->vIcmsDeson));
            XmlHelper::append($grupo, 'motDesICMS', preg_replace('/\D/', '', (string) $imp->motivoDesoneracao) ?: '9');
        }
    }

    private static function appendIcmsValores(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        $valores = self::resolveIcmsValores($imp);

        if ($valores === null) {
            return;
        }

        XmlHelper::append($grupo, 'modBC', '3');
        XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($valores['vBc']));
        XmlHelper::append($grupo, 'pICMS', NumberFormatter::decimal($valores['pIcms'], 4));
        XmlHelper::append($grupo, 'vICMS', NumberFormatter::decimal($valores['vIcms']));
    }

    private static function normalizeCst(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
