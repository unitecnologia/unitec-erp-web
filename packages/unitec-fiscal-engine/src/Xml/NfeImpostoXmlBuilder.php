<?php

namespace Unitec\FiscalEngine\Xml;

use DOMElement;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
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

        $cst = $imp->cstPisResolvido();
        if ($cst === '') {
            throw new FiscalEngineException(
                'CST de PIS não informado no item. Informe no cadastro do produto/operação '
                .'(ex.: 01/02, 04–09, 49/99). Não inventar CST 01 nem 07.',
            );
        }

        if ($cst === '03') {
            throw new FiscalEngineException(
                'CST de PIS 03 (PISQtde) ainda não é suportado na emissão online.',
            );
        }

        // 49/99 → PISOutr (vBC, pPIS, vPIS obrigatórios no XSD; podem ser zero).
        if (in_array($cst, ['49', '99'], true)) {
            self::assertOutrConsistente($imp->vBcPis, $imp->pPis, $imp->vPis, 'PIS');
            $grupo = $pis->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PISOutr');
            $pis->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $cst);
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcPis));
            XmlHelper::append($grupo, 'pPIS', NumberFormatter::decimal($imp->pPis, 4));
            XmlHelper::append($grupo, 'vPIS', NumberFormatter::decimal($imp->vPis));

            return;
        }

        if (in_array($cst, ['01', '02'], true)) {
            $grupo = $pis->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PISAliq');
            $pis->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $cst);
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcPis));
            XmlHelper::append($grupo, 'pPIS', NumberFormatter::decimal($imp->pPis, 4));
            XmlHelper::append($grupo, 'vPIS', NumberFormatter::decimal($imp->vPis));

            return;
        }

        // PISNT: 04 monofásico, 05 ST da contribuição, 06–09.
        // Distinto do grupo opcional PISST (retenção) — não inventar/emitir PISST aqui.
        if (! in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            throw new FiscalEngineException(
                'CST de PIS "'.$cst.'" inválido para o XML. Use 01/02, 04–09 ou 49/99 conforme a operação.',
            );
        }

        $grupo = $pis->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'PISNT');
        $pis->appendChild($grupo);
        XmlHelper::append($grupo, 'CST', $cst);
    }

    public static function appendCofins(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        $cofins = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINS');
        $imposto->appendChild($cofins);

        $cst = $imp->cstCofinsResolvido();
        if ($cst === '') {
            throw new FiscalEngineException(
                'CST de COFINS não informado no item. Informe no cadastro do produto/operação '
                .'(ex.: 01/02, 04–09, 49/99). Não inventar CST 01 nem 07.',
            );
        }

        if ($cst === '03') {
            throw new FiscalEngineException(
                'CST de COFINS 03 (COFINSQtde) ainda não é suportado na emissão online.',
            );
        }

        if (in_array($cst, ['49', '99'], true)) {
            self::assertOutrConsistente($imp->vBcCofins, $imp->pCofins, $imp->vCofins, 'COFINS');
            $grupo = $cofins->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINSOutr');
            $cofins->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $cst);
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcCofins));
            XmlHelper::append($grupo, 'pCOFINS', NumberFormatter::decimal($imp->pCofins, 4));
            XmlHelper::append($grupo, 'vCOFINS', NumberFormatter::decimal($imp->vCofins));

            return;
        }

        if (in_array($cst, ['01', '02'], true)) {
            $grupo = $cofins->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINSAliq');
            $cofins->appendChild($grupo);
            XmlHelper::append($grupo, 'CST', $cst);
            XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($imp->vBcCofins));
            XmlHelper::append($grupo, 'pCOFINS', NumberFormatter::decimal($imp->pCofins, 4));
            XmlHelper::append($grupo, 'vCOFINS', NumberFormatter::decimal($imp->vCofins));

            return;
        }

        // COFINSNT: 04 monofásico, 05 ST da contribuição, 06–09 (≠ grupo COFINSST).
        if (! in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            throw new FiscalEngineException(
                'CST de COFINS "'.$cst.'" inválido para o XML. Use 01/02, 04–09 ou 49/99 conforme a operação.',
            );
        }

        $grupo = $cofins->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'COFINSNT');
        $cofins->appendChild($grupo);
        XmlHelper::append($grupo, 'CST', $cst);
    }

    /**
     * ICMSUFDest (NA01) — após PIS/COFINS; valores NÃO entram em vNF.
     * Ordem XSD: vBCUFDest, [vBCFCPUFDest], pFCPUFDest, pICMSUFDest, pICMSInter,
     * pICMSInterPart, vFCPUFDest, vICMSUFDest, vICMSUFRemet.
     */
    public static function appendIcmsUfDest(DOMElement $imposto, ItemImpostoDto $imp): void
    {
        if (! $imp->hasIcmsUfDest()) {
            return;
        }

        $grupo = $imposto->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ICMSUFDest');
        $imposto->appendChild($grupo);

        XmlHelper::append($grupo, 'vBCUFDest', NumberFormatter::decimal($imp->vBcUfDest));
        if ($imp->pFcpUfDest > 0 || $imp->vFcpUfDest > 0) {
            $vBcFcp = $imp->vBcFcpUfDest > 0 ? $imp->vBcFcpUfDest : $imp->vBcUfDest;
            XmlHelper::append($grupo, 'vBCFCPUFDest', NumberFormatter::decimal($vBcFcp));
        }
        XmlHelper::append($grupo, 'pFCPUFDest', NumberFormatter::decimal($imp->pFcpUfDest, 4));
        XmlHelper::append($grupo, 'pICMSUFDest', NumberFormatter::decimal($imp->pIcmsUfDest, 4));
        XmlHelper::append($grupo, 'pICMSInter', NumberFormatter::decimal($imp->pIcmsInter, 4));
        XmlHelper::append($grupo, 'pICMSInterPart', NumberFormatter::decimal($imp->pIcmsInterPart, 4));
        XmlHelper::append($grupo, 'vFCPUFDest', NumberFormatter::decimal($imp->vFcpUfDest));
        XmlHelper::append($grupo, 'vICMSUFDest', NumberFormatter::decimal($imp->vIcmsUfDest));
        XmlHelper::append($grupo, 'vICMSUFRemet', NumberFormatter::decimal($imp->vIcmsUfRemet));
    }

    /**
     * PISOutr/COFINSOutr: campos obrigatórios; se BC e alíquota > 0, valor deve bater (tol. 0,02).
     */
    private static function assertOutrConsistente(float $vBc, float $pAliq, float $vTrib, string $imposto): void
    {
        if ($vBc < 0 || $pAliq < 0 || $vTrib < 0) {
            throw new FiscalEngineException(
                $imposto.'Outr com valores negativos (vBC/p/v). Verifique o item.',
            );
        }

        if ($vBc > 0 && $pAliq > 0) {
            $esperado = round($vBc * $pAliq / 100, 2);
            if (abs($esperado - round($vTrib, 2)) > 0.02) {
                throw new FiscalEngineException(
                    $imposto.'Outr inconsistente: vBC='.NumberFormatter::decimal($vBc)
                    .' p='.NumberFormatter::decimal($pAliq, 4)
                    .' v='.NumberFormatter::decimal($vTrib)
                    .' (esperado ≈ '.NumberFormatter::decimal($esperado).').',
                );
            }
        }
    }

    /**
     * @param  array<string, float>  $acc
     * @return array<string, float>
     */
    public static function somarTotais(ItemImpostoDto $imp, array $acc): array
    {
        $acc['vFcp'] = (float) ($acc['vFcp'] ?? 0);
        $acc['vBcSt'] = (float) ($acc['vBcSt'] ?? 0);
        $acc['vSt'] = (float) ($acc['vSt'] ?? 0);
        $acc['vFcpSt'] = (float) ($acc['vFcpSt'] ?? 0);
        $acc['vFcpStRet'] = (float) ($acc['vFcpStRet'] ?? 0);
        $acc['vFcpUfDest'] = (float) ($acc['vFcpUfDest'] ?? 0);
        $acc['vIcmsUfDest'] = (float) ($acc['vIcmsUfDest'] ?? 0);
        $acc['vIcmsUfRemet'] = (float) ($acc['vIcmsUfRemet'] ?? 0);

        $icmsValores = self::resolveIcmsValores($imp);
        if ($icmsValores !== null) {
            $acc['vBc'] += $icmsValores['vBc'];
            $acc['vIcms'] += $icmsValores['vIcms'];
        }

        $acc['vIcmsDeson'] += $imp->vIcmsDeson;
        $acc['vIcmsDesonDeduzivel'] = (float) ($acc['vIcmsDesonDeduzivel'] ?? 0);
        if ($imp->deduzIcmsDesonDoTotal()) {
            $acc['vIcmsDesonDeduzivel'] += round($imp->vIcmsDeson, 2);
        }
        $acc['vIpi'] += $imp->vIpi;
        $acc['vPis'] += $imp->vPis;
        $acc['vCofins'] += $imp->vCofins;

        // ST cobrada e FCP da operação entram nos totais. ST/FCP retidos NÃO entram em vST.
        if ($imp->hasStCobrada()) {
            $acc['vBcSt'] += round($imp->vBcSt, 2);
            $acc['vSt'] += round($imp->vIcmsSt, 2);
        }
        if ($imp->hasFcpProprio()) {
            $acc['vFcp'] += round($imp->vFcp, 2);
        }
        if ($imp->hasFcpSt()) {
            $acc['vFcpSt'] += round($imp->vFcpSt, 2);
        }
        if ($imp->hasFcpStRet()) {
            $acc['vFcpStRet'] += round($imp->vFcpStRet, 2);
        }

        // DIFAL: totaliza em ICMSTot, sem alterar vNF.
        if ($imp->hasIcmsUfDest()) {
            $acc['vFcpUfDest'] += round($imp->vFcpUfDest, 2);
            $acc['vIcmsUfDest'] += round($imp->vIcmsUfDest, 2);
            $acc['vIcmsUfRemet'] += round($imp->vIcmsUfRemet, 2);
        }

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

        if (in_array($csosn, ['201', '202', '203'], true)) {
            self::appendIcmsValores($grupo, $imp);
            self::appendIcmsStCobrada($grupo, $imp);
            self::appendFcpStCobrada($grupo, $imp);

            return;
        }

        if ($csosn === '500') {
            self::appendIcmsStRetida($grupo, $imp);
            self::appendFcpStRetida($grupo, $imp);

            return;
        }

        if ($csosn === '900' || ($imp->hasIcmsDetalhe() && ! in_array($csosn, ['102', '103', '300', '400', '500'], true))) {
            self::appendIcmsValores($grupo, $imp);
            if ($imp->hasStCobrada()) {
                self::appendIcmsStCobrada($grupo, $imp);
                self::appendFcpStCobrada($grupo, $imp);
            }
        }

        self::appendFcpProprioSeAplicavel($grupo, $imp, $csosn);
    }

    private static function appendIcmsNormal(DOMElement $icms, ItemImpostoDto $imp): void
    {
        $cst = $imp->cstIcmsResolvido();
        $tag = 'ICMS' . $cst;
        $grupo = $icms->ownerDocument->createElementNS(XmlHelper::NFE_NS, $tag);
        $icms->appendChild($grupo);

        XmlHelper::append($grupo, 'orig', (string) $imp->origem);
        XmlHelper::append($grupo, 'CST', $cst);

        if ($cst === '60') {
            self::appendIcmsStRetida($grupo, $imp);
            self::appendFcpStRetida($grupo, $imp);

            return;
        }

        if ($cst === '30') {
            // Isenta + ST: sem ICMS próprio; só ST/FCP-ST.
            self::appendIcmsStCobrada($grupo, $imp);
            self::appendFcpStCobrada($grupo, $imp);
            self::appendIcmsDesoneracao($grupo, $imp);

            return;
        }

        if (in_array($cst, ['00', '10', '20', '51', '70', '90'], true)) {
            self::appendIcmsValores($grupo, $imp);
            self::appendFcpProprioSeAplicavel($grupo, $imp, $cst);
        }

        if (in_array($cst, ['10', '70', '90'], true) && $imp->hasStCobrada()) {
            self::appendIcmsStCobrada($grupo, $imp);
            self::appendFcpStCobrada($grupo, $imp);
        }

        self::appendIcmsDesoneracao($grupo, $imp);
    }

    /**
     * NT 2023.004 v1.11: vICMSDeson + motDesICMS + indDeduzDeson.
     * motDesICMS=7 (SUFRAMA) → indDeduzDeson obrigatoriamente 1.
     * Demais motivos: 0 ou 1; ausente no DTO → 0 (Exceção 4 W16-10).
     */
    private static function appendIcmsDesoneracao(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        if ($imp->vIcmsDeson <= 0 || ! self::filled($imp->motivoDesoneracao)) {
            return;
        }

        $mot = preg_replace('/\D/', '', (string) $imp->motivoDesoneracao) ?: '9';
        // Normaliza "07" → "7" para bater a tabela SEFAZ.
        $mot = (string) ((int) $mot);

        XmlHelper::append($grupo, 'vICMSDeson', NumberFormatter::decimal($imp->vIcmsDeson));
        XmlHelper::append($grupo, 'motDesICMS', $mot);
        $ind = ($mot === '7' || $imp->indDeduzDeson === 1) ? '1' : '0';
        XmlHelper::append($grupo, 'indDeduzDeson', $ind);
    }

    private static function appendIcmsStCobrada(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        XmlHelper::append($grupo, 'modBCST', $imp->modBcSt !== null && $imp->modBcSt !== '' ? $imp->modBcSt : '4');
        if ($imp->pMvaSt > 0) {
            XmlHelper::append($grupo, 'pMVAST', NumberFormatter::decimal($imp->pMvaSt, 4));
        }
        if ($imp->pRedBcSt > 0) {
            XmlHelper::append($grupo, 'pRedBCST', NumberFormatter::decimal($imp->pRedBcSt, 4));
        }
        XmlHelper::append($grupo, 'vBCST', NumberFormatter::decimal($imp->vBcSt));
        XmlHelper::append($grupo, 'pICMSST', NumberFormatter::decimal($imp->pIcmsSt, 4));
        XmlHelper::append($grupo, 'vICMSST', NumberFormatter::decimal($imp->vIcmsSt));
    }

    private static function appendIcmsStRetida(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        // Ordem XSD ICMS60/ICMSSN500: vBCSTRet → pST → vICMSSubstituto → vICMSSTRet → FCPSTRet
        XmlHelper::append($grupo, 'vBCSTRet', NumberFormatter::decimal($imp->vBcStRet));
        if ($imp->pStRet > 0) {
            XmlHelper::append($grupo, 'pST', NumberFormatter::decimal($imp->pStRet, 4));
        }
        if ($imp->vIcmsSubstituto > 0) {
            XmlHelper::append($grupo, 'vICMSSubstituto', NumberFormatter::decimal($imp->vIcmsSubstituto));
        }
        XmlHelper::append($grupo, 'vICMSSTRet', NumberFormatter::decimal($imp->vIcmsStRet));
    }

    private static function appendFcpProprioSeAplicavel(DOMElement $grupo, ItemImpostoDto $imp, string $cstOuCsosn): void
    {
        if (! $imp->hasFcpProprio()) {
            return;
        }

        // FCP próprio nos grupos que o leiaute admite com ICMS próprio.
        if (! in_array($cstOuCsosn, ['00', '10', '20', '70', '90', '900'], true)) {
            return;
        }

        if ($imp->vBcFcp > 0) {
            XmlHelper::append($grupo, 'vBCFCP', NumberFormatter::decimal($imp->vBcFcp));
        }
        XmlHelper::append($grupo, 'pFCP', NumberFormatter::decimal($imp->pFcp, 4));
        XmlHelper::append($grupo, 'vFCP', NumberFormatter::decimal($imp->vFcp));
    }

    private static function appendFcpStCobrada(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        if (! $imp->hasFcpSt()) {
            return;
        }

        if ($imp->vBcFcpSt > 0) {
            XmlHelper::append($grupo, 'vBCFCPST', NumberFormatter::decimal($imp->vBcFcpSt));
        }
        XmlHelper::append($grupo, 'pFCPST', NumberFormatter::decimal($imp->pFcpSt, 4));
        XmlHelper::append($grupo, 'vFCPST', NumberFormatter::decimal($imp->vFcpSt));
    }

    private static function appendFcpStRetida(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        if (! $imp->hasFcpStRet()) {
            return;
        }

        if ($imp->vBcFcpStRet > 0) {
            XmlHelper::append($grupo, 'vBCFCPSTRet', NumberFormatter::decimal($imp->vBcFcpStRet));
        }
        if ($imp->pFcpStRet > 0) {
            XmlHelper::append($grupo, 'pFCPSTRet', NumberFormatter::decimal($imp->pFcpStRet, 4));
        }
        XmlHelper::append($grupo, 'vFCPSTRet', NumberFormatter::decimal($imp->vFcpStRet));
    }

    private static function appendIcmsValores(DOMElement $grupo, ItemImpostoDto $imp): void
    {
        $valores = self::resolveIcmsValores($imp);

        if ($valores === null) {
            return;
        }

        XmlHelper::append($grupo, 'modBC', $imp->modBc !== null && $imp->modBc !== '' ? $imp->modBc : '3');

        $emitirPRedBc = $imp->pRedBc > 0 && (
            $imp->cstIcmsResolvido() === '20'
            || ($imp->usesSimples() && $imp->csosnResolvido() === '900')
        );

        // XSD ICMSSN900: modBC → vBC → pRedBC → pICMS → vICMS
        // XSD ICMS20:     modBC → pRedBC → vBC → pICMS → vICMS
        $pRedBcAposVBc = $imp->usesSimples() && $imp->csosnResolvido() === '900';

        if ($emitirPRedBc && ! $pRedBcAposVBc) {
            XmlHelper::append($grupo, 'pRedBC', NumberFormatter::decimal($imp->pRedBc, 4));
        }

        XmlHelper::append($grupo, 'vBC', NumberFormatter::decimal($valores['vBc']));

        if ($emitirPRedBc && $pRedBcAposVBc) {
            XmlHelper::append($grupo, 'pRedBC', NumberFormatter::decimal($imp->pRedBc, 4));
        }

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
