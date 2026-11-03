<?php

namespace App\Support\Fiscal;

use App\Models\NfeItem;
use App\Models\Product;
use DateTimeInterface;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;

final class IbscbsImpostoFactory
{
    public static function fromNfeItem(
        NfeItem $item,
        int $origem,
        string $csosn,
        float $vIcms = 0.0,
        float $vTotTrib = 0.0,
        int $crt = 1,
        ?DateTimeInterface $dataEmissao = null,
    ): ItemImpostoDto {
        $product = $item->product;
        $csosnNorm = self::digits($csosn, 3) ?: self::digits((string) ($item->csosn ?? ''), 3) ?: '102';
        if (MeiFiscalNormalizer::isMeiCrt($crt)) {
            $csosnNorm = MeiFiscalNormalizer::normalizeItem(
                $csosnNorm,
                (string) ($item->cfop ?: '5102'),
                MeiFiscalNormalizer::MODELO_NFE,
            )['csosn'];
        }
        $cstIcms = self::digits((string) ($item->cst ?? ''), 2);

        $vBcIcms = round((float) ($item->base_icms ?? 0), 2);
        $vIcmsItem = round((float) ($item->valor_icms ?? $vIcms), 2);
        $pIcms = (float) ($item->aliq_icms ?? 0);

        $emitirIbscbs = IbscbsApplicabilityResolver::deveEmitir(
            $crt,
            $dataEmissao ?? now(),
        );

        $pRedIbs = 0.0;
        $pRedCbs = 0.0;
        $cstIbsCbs = null;
        $cClassTrib = null;
        $vBcIbscbs = 0.0;
        $pIbsUf = 0.0;
        $vIbsUf = 0.0;
        $pIbsMun = 0.0;
        $vIbsMun = 0.0;
        $pCbs = 0.0;
        $vCbs = 0.0;

        if ($emitirIbscbs) {
            $pRedIbs = self::resolveReducao($item->p_red_ibs ?? null, (float) ($product?->reducao_ibs ?? 0));
            $pRedCbs = self::resolveReducao($item->p_red_cbs ?? null, (float) ($product?->reducao_cbs ?? 0));
            $cstIbsCbs = self::optionalDigits((string) ($item->cst_ibs_cbs ?: $product?->iva_cst ?: ''), 3);
            $cClassTrib = self::optionalDigits((string) ($item->class_trib ?: $product?->cclass_trib ?: ''), 6);
            $vBcIbscbs = round((float) ($item->bc_ibs ?: $item->total ?: 0), 2);
            $pIbsUf = (float) ($item->alq_ibs_uf ?: $product?->aliq_ibs_uf ?: 0);
            $vIbsUf = self::resolveValor($item->v_ibs_uf, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_ibs_uf ?: $product?->aliq_ibs_uf ?: 0), $pRedIbs);
            $pIbsMun = (float) ($item->alq_ibs_mun ?: $product?->aliq_ibs_mun ?: 0);
            $vIbsMun = self::resolveValor($item->v_ibs_mun, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_ibs_mun ?: $product?->aliq_ibs_mun ?: 0), $pRedIbs);
            $pCbs = (float) ($item->alq_cbs ?: $product?->aliq_cbs ?: 0);
            $vCbs = self::resolveValor($item->v_cbs, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_cbs ?: $product?->aliq_cbs ?: 0), $pRedCbs);
        }

        $cstPis = self::optionalDigits(
            (string) ($item->cst_pis ?: $product?->cst_saida ?? ''),
            2,
        );
        $cstCofins = self::optionalDigits(
            (string) ($item->cst_cofins ?: $product?->cst_cofins ?: $product?->cst_saida ?? ''),
            2,
        );
        $pPis = (float) ($item->aliq_pis_icms ?? 0);
        $vBcPis = round((float) ($item->base_pis_icms ?? 0), 2);
        $vPis = round((float) ($item->valor_pis_icms ?? 0), 2);
        $pCofins = (float) ($item->aliq_cofins_icms ?? 0);
        $vBcCofins = round((float) ($item->base_cofins_icms ?? 0), 2);
        $vCofins = round((float) ($item->valor_cofins_icms ?? 0), 2);

        // NT (04–09): sem BC/alíquota/valor no XML — zera totais mesmo se o item antigo tiver valores.
        if ($cstPis !== null && PisCofinsCst::isNt($cstPis)) {
            $pPis = 0.0;
            $vBcPis = 0.0;
            $vPis = 0.0;
        }
        if ($cstCofins !== null && PisCofinsCst::isNt($cstCofins)) {
            $pCofins = 0.0;
            $vBcCofins = 0.0;
            $vCofins = 0.0;
        }

        $vBcStItem = round((float) ($item->base_icms_st ?? 0), 2);
        $pIcmsSt = (float) ($item->aliq_icms_st ?? 0);
        $vIcmsSt = round((float) ($item->valor_icms_st ?? 0), 2);
        $temStCobradaVals = $vBcStItem > 0 || $vIcmsSt > 0 || $pIcmsSt > 0;

        $modoSt = IcmsStFcp::modo(
            $crt,
            $cstIcms !== '' ? $cstIcms : null,
            $csosnNorm,
            $temStCobradaVals,
        );

        // ST retida: preferir colunas *_ret; compatibilidade CST 60/CSOSN 500 com campos antigos.
        $vBcStRet = round((float) ($item->base_icms_st_ret ?? 0), 2);
        $pStRet = (float) ($item->p_st_ret ?? 0);
        $vIcmsStRet = round((float) ($item->valor_icms_st_ret ?? 0), 2);
        if ($modoSt === IcmsStFcp::MODO_RETIDA && $vBcStRet <= 0 && $vIcmsStRet <= 0 && $temStCobradaVals) {
            $vBcStRet = $vBcStItem;
            $pStRet = $pIcmsSt;
            $vIcmsStRet = $vIcmsSt;
            $vBcStItem = 0.0;
            $pIcmsSt = 0.0;
            $vIcmsSt = 0.0;
        }
        if ($modoSt !== IcmsStFcp::MODO_COBRADA) {
            $vBcStItem = 0.0;
            $pIcmsSt = 0.0;
            $vIcmsSt = 0.0;
        }
        if ($modoSt !== IcmsStFcp::MODO_RETIDA) {
            $vBcStRet = 0.0;
            $pStRet = 0.0;
            $vIcmsStRet = 0.0;
        }

        $pFcp = (float) ($item->p_fcp ?? 0);
        if ($pFcp <= 0) {
            $pFcp = (float) ($product?->fcp_pct ?? 0);
        }
        $vFcp = round((float) ($item->vfcp ?? 0), 2);
        $vBcFcp = round((float) ($item->v_bc_fcp ?? 0), 2);
        if ($vFcp > 0 && $vBcFcp <= 0 && $pFcp > 0) {
            $vBcFcp = round($vFcp / ($pFcp / 100), 2);
        }
        if ($vFcp <= 0) {
            $pFcp = 0.0;
            $vBcFcp = 0.0;
        }

        $vBcFcpSt = round((float) ($item->v_bc_fcp_st ?? 0), 2);
        $pFcpSt = (float) ($item->p_fcp_st ?? 0);
        $vFcpSt = round((float) ($item->v_fcp_st ?? 0), 2);
        // pFCPST é alíquota própria da operação — NÃO presumir = fcp_pct (pFCP).
        // Se houver vFCPST sem pFCPST, a validação de emissão exige o preenchimento.
        if ($modoSt === IcmsStFcp::MODO_COBRADA && $vFcpSt > 0 && $vBcFcpSt <= 0 && $pFcpSt > 0) {
            $vBcFcpSt = round($vFcpSt / ($pFcpSt / 100), 2);
        }
        if ($modoSt !== IcmsStFcp::MODO_COBRADA) {
            $vBcFcpSt = 0.0;
            $pFcpSt = 0.0;
            $vFcpSt = 0.0;
        }

        $vBcFcpStRet = round((float) ($item->v_bc_fcp_st_ret ?? 0), 2);
        $pFcpStRet = (float) ($item->p_fcp_st_ret ?? 0);
        $vFcpStRet = round((float) ($item->v_fcp_st_ret ?? 0), 2);
        if ($modoSt !== IcmsStFcp::MODO_RETIDA) {
            $vBcFcpStRet = 0.0;
            $pFcpStRet = 0.0;
            $vFcpStRet = 0.0;
        }

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosnNorm,
            vBc: $vBcIcms,
            vIcms: $vIcmsItem,
            vPis: $vPis,
            vCofins: $vCofins,
            vTotTrib: $vTotTrib,
            cstIbsCbs: $cstIbsCbs,
            cClassTrib: $cClassTrib,
            vBcIbscbs: $vBcIbscbs,
            pIbsUf: $pIbsUf,
            vIbsUf: $vIbsUf,
            pIbsMun: $pIbsMun,
            vIbsMun: $vIbsMun,
            pCbs: $pCbs,
            vCbs: $vCbs,
            pRedIbs: $pRedIbs,
            pRedCbs: $pRedCbs,
            cstIcms: $cstIcms !== '' ? $cstIcms : null,
            pIcms: $pIcms,
            pPis: $pPis,
            vBcPis: $vBcPis,
            cstPis: $cstPis,
            pCofins: $pCofins,
            vBcCofins: $vBcCofins,
            cstCofins: $cstCofins,
            vIpi: round((float) ($item->valor_ipi ?? 0), 2),
            pIpi: (float) ($item->aliq_ipi ?? 0),
            vBcIpi: round((float) ($item->base_ipi ?? 0), 2),
            cstIpi: self::optionalDigits((string) ($item->cst_ipi ?? ''), 2),
            vIcmsDeson: round((float) ($item->valor_desoneracao ?? 0), 2),
            motivoDesoneracao: filled($item->motivo_desoneracao) ? (string) $item->motivo_desoneracao : null,
            indDeduzDeson: self::resolveIndDeduzDeson($item),
            crt: $crt,
            pRedBc: (float) ($item->p_red_bc_icms ?? 0),
            modBc: filled($item->mod_bc_icms) ? (string) $item->mod_bc_icms : null,
            vBcSt: $vBcStItem,
            pIcmsSt: $pIcmsSt,
            vIcmsSt: $vIcmsSt,
            pMvaSt: (float) ($item->p_mva_st ?? $product?->mva_pct ?? 0),
            pRedBcSt: (float) ($item->p_red_bc_st ?? 0),
            modBcSt: filled($item->mod_bc_st ?? null) ? (string) $item->mod_bc_st : null,
            vBcFcp: $vBcFcp,
            pFcp: $pFcp,
            vFcp: $vFcp,
            vBcFcpSt: $vBcFcpSt,
            pFcpSt: $pFcpSt,
            vFcpSt: $vFcpSt,
            vBcStRet: $vBcStRet,
            pStRet: $pStRet,
            vIcmsSubstituto: $modoSt === IcmsStFcp::MODO_RETIDA
                ? round((float) ($item->v_icms_substituto ?? 0), 2)
                : 0.0,
            vIcmsStRet: $vIcmsStRet,
            vBcFcpStRet: $vBcFcpStRet,
            pFcpStRet: $pFcpStRet,
            vFcpStRet: $vFcpStRet,
        );
    }

    public static function fromProduct(
        ?Product $product,
        float $base,
        float $vTotTrib = 0.0,
        int $crt = 1,
        ?DateTimeInterface $dataEmissao = null,
    ): ItemImpostoDto {
        $origem = (int) ($product?->origem ?? 0);
        $csosn = (string) ($product?->csosn ?: '102');
        if (MeiFiscalNormalizer::isMeiCrt($crt)) {
            $csosn = MeiFiscalNormalizer::normalizeItem(
                $csosn,
                '5102',
                MeiFiscalNormalizer::MODELO_NFCE,
            )['csosn'];
        }
        $vBcBruta = round($base, 2);

        $emitirIbscbs = IbscbsApplicabilityResolver::deveEmitir(
            $crt,
            $dataEmissao ?? now(),
        );

        $cstIbs = null;
        $cClass = null;
        $pIbsUf = 0.0;
        $pIbsMun = 0.0;
        $pCbs = 0.0;
        $pRedIbs = 0.0;
        $pRedCbs = 0.0;
        $vBcIbscbs = 0.0;
        $vIbsUf = 0.0;
        $vIbsMun = 0.0;
        $vCbs = 0.0;

        if ($emitirIbscbs) {
            $cstIbs = self::digits((string) ($product?->iva_cst ?? ''), 3);
            $cClass = self::digits((string) ($product?->cclass_trib ?? ''), 6);
            $pIbsUf = (float) ($product?->aliq_ibs_uf ?? 0);
            $pIbsMun = (float) ($product?->aliq_ibs_mun ?? 0);
            $pCbs = (float) ($product?->aliq_cbs ?? 0);
            $pRedIbs = (float) ($product?->reducao_ibs ?? 0);
            $pRedCbs = (float) ($product?->reducao_cbs ?? 0);
            $vBcIbscbs = $vBcBruta;
            $vIbsUf = self::calcValor($vBcBruta, $pIbsUf, $pRedIbs);
            $vIbsMun = self::calcValor($vBcBruta, $pIbsMun, $pRedIbs);
            $vCbs = self::calcValor($vBcBruta, $pCbs, $pRedCbs);
        }

        $cstIbsCbs = $cstIbs !== null && $cstIbs !== '' ? $cstIbs : null;
        $cClassTrib = $cClass !== null && $cClass !== '' ? $cClass : null;

        // PIS/COFINS sempre do cadastro (não inventa CST 07 por ser Simples).
        $pis = self::calcularPisCofinsProduto(
            $product,
            $vBcBruta,
            cstField: 'cst_saida',
            aliqField: 'aliq_pis',
        );
        $cofins = self::calcularPisCofinsProduto(
            $product,
            $vBcBruta,
            cstField: 'cst_cofins',
            aliqField: 'aliq_cofins',
            cstFallbackField: 'cst_saida',
        );

        // CRT 1/4: ICMS via CSOSN; PIS/COFINS do produto (sem forçar isenção).
        if (RegimeCrtMapper::usaIcmsCsosn($crt)) {
            return new ItemImpostoDto(
                origem: $origem,
                csosn: $csosn,
                vPis: $pis['v_trib'],
                vCofins: $cofins['v_trib'],
                vTotTrib: $vTotTrib,
                cstIbsCbs: $cstIbsCbs,
                cClassTrib: $cClassTrib,
                vBcIbscbs: $vBcIbscbs,
                pIbsUf: $pIbsUf,
                vIbsUf: $vIbsUf,
                pIbsMun: $pIbsMun,
                vIbsMun: $vIbsMun,
                pCbs: $pCbs,
                vCbs: $vCbs,
                pRedIbs: $pRedIbs,
                pRedCbs: $pRedCbs,
                pPis: $pis['p_aliq'],
                vBcPis: $pis['v_bc'],
                cstPis: $pis['cst'],
                pCofins: $cofins['p_aliq'],
                vBcCofins: $cofins['v_bc'],
                cstCofins: $cofins['cst'],
                crt: $crt,
            );
        }

        // CRT 2 e 3: ICMS com CST do produto (nunca CSOSN).
        $icms = self::calcularIcmsProduto($product, $vBcBruta);

        // CRT 2: ICMS CST + PIS/COFINS do produto (alíquotas do cadastro; não Presumido automático).
        // CRT 3: idem com parametrização completa do produto.
        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosn,
            vBc: $icms['v_bc'],
            vIcms: $icms['v_icms'],
            vPis: $pis['v_trib'],
            vCofins: $cofins['v_trib'],
            vTotTrib: $vTotTrib,
            cstIbsCbs: $cstIbsCbs,
            cClassTrib: $cClassTrib,
            vBcIbscbs: $vBcIbscbs,
            pIbsUf: $pIbsUf,
            vIbsUf: $vIbsUf,
            pIbsMun: $pIbsMun,
            vIbsMun: $vIbsMun,
            pCbs: $pCbs,
            vCbs: $vCbs,
            pRedIbs: $pRedIbs,
            pRedCbs: $pRedCbs,
            cstIcms: $icms['cst'],
            pIcms: $icms['p_icms'],
            pPis: $pis['p_aliq'],
            vBcPis: $pis['v_bc'],
            cstPis: $pis['cst'],
            pCofins: $cofins['p_aliq'],
            vBcCofins: $cofins['v_bc'],
            cstCofins: $cofins['cst'],
            crt: $crt,
            pRedBc: $icms['p_red_bc'],
            modBc: $icms['mod_bc'],
        );
    }

    /**
     * @return array{cst: ?string, v_bc: float, p_icms: float, v_icms: float, p_red_bc: float, mod_bc: ?string}
     */
    private static function calcularIcmsProduto(?Product $product, float $base): array
    {
        $cst = self::optionalDigits((string) ($product?->cst_icms ?? ''), 2);
        $pIcms = (float) ($product?->aliq_icms ?? 0);
        $pRed = max(0.0, (float) ($product?->reducao_base_pct ?? 0));

        // CST sem destaque de ICMS (isento / NT / ST retido sem BC própria no PDV).
        if ($cst !== null && in_array($cst, ['40', '41', '50', '60'], true)) {
            return [
                'cst' => $cst,
                'v_bc' => 0.0,
                'p_icms' => 0.0,
                'v_icms' => 0.0,
                'p_red_bc' => 0.0,
                'mod_bc' => null,
            ];
        }

        $vBc = $pRed > 0 ? round($base * (1 - ($pRed / 100)), 2) : $base;
        $vIcms = $pIcms > 0 ? round($vBc * $pIcms / 100, 2) : 0.0;

        return [
            'cst' => $cst,
            'v_bc' => $vBc,
            'p_icms' => $pIcms,
            'v_icms' => $vIcms,
            'p_red_bc' => $pRed,
            'mod_bc' => $pIcms > 0 || $pRed > 0 ? '3' : null,
        ];
    }

    /**
     * @return array{cst: ?string, v_bc: float, p_aliq: float, v_trib: float}
     */
    private static function calcularPisCofinsProduto(
        ?Product $product,
        float $base,
        string $cstField,
        string $aliqField,
        ?string $cstFallbackField = null,
    ): array {
        $cstRaw = (string) ($product?->{$cstField} ?? '');
        if ($cstRaw === '' && $cstFallbackField !== null) {
            $cstRaw = (string) ($product?->{$cstFallbackField} ?? '');
        }
        $cst = self::optionalDigits($cstRaw, 2);
        $pAliq = (float) ($product?->{$aliqField} ?? 0);

        // PISNT/COFINSNT (CST 04 monofásico, 05 ST contribuição, 06–09): sem BC/valor no XML.
        if ($cst !== null && in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
            return [
                'cst' => $cst,
                'v_bc' => 0.0,
                'p_aliq' => 0.0,
                'v_trib' => 0.0,
            ];
        }

        // 01/02 (alíquota) e 49/99 (outras → PISOutr/COFINSOutr): BC + alíquota do produto.
        $vBc = $base;
        $vTrib = $pAliq > 0 ? round($vBc * $pAliq / 100, 2) : 0.0;

        return [
            'cst' => $cst,
            'v_bc' => $vBc,
            'p_aliq' => $pAliq,
            'v_trib' => $vTrib,
        ];
    }

    private static function resolveReducao(mixed $itemValue, float $productFallback): float
    {
        if ($itemValue !== null && $itemValue !== '') {
            return (float) $itemValue;
        }

        return $productFallback;
    }

    private static function resolveValor(mixed $stored, float $vBc, float $pAliq, float $pRed): float
    {
        if ($stored !== null && (float) $stored > 0) {
            return round((float) $stored, 2);
        }

        return self::calcValor($vBc, $pAliq, $pRed);
    }

    private static function calcValor(float $vBc, float $pAliq, float $pRed): float
    {
        $pEfet = $pRed > 0 ? $pAliq * (1 - ($pRed / 100)) : $pAliq;

        return round($vBc * $pEfet / 100, 2);
    }

    private static function digits(string $value, int $pad): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, 0, $pad), $pad, '0', STR_PAD_LEFT);
    }

    private static function optionalDigits(string $value, int $pad): ?string
    {
        $digits = self::digits($value, $pad);

        return $digits !== '' ? $digits : null;
    }

    /**
     * NT 2023.004 v1.11: indDeduzDeson (N28b).
     * motDesICMS=7 (SUFRAMA) com vICMSDeson > 0 → obrigatoriamente 1.
     *
     * @param  \App\Models\NfeItem  $item
     */
    private static function resolveIndDeduzDeson(object $item): ?int
    {
        $vDeson = round((float) ($item->valor_desoneracao ?? 0), 2);
        $mot = preg_replace('/\D/', '', (string) ($item->motivo_desoneracao ?? '')) ?? '';
        $mot = $mot === '' ? '' : (string) ((int) $mot);

        if ($vDeson > 0 && $mot === '7') {
            return 1;
        }

        if (! isset($item->ind_deduz_deson) || $item->ind_deduz_deson === null || $item->ind_deduz_deson === '') {
            return null;
        }

        return ((int) $item->ind_deduz_deson) === 1 ? 1 : 0;
    }
}
