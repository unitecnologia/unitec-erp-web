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

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosnNorm,
            vBc: $vBcIcms,
            vIcms: $vIcmsItem,
            vPis: round((float) ($item->valor_pis_icms ?? 0), 2),
            vCofins: round((float) ($item->valor_cofins_icms ?? 0), 2),
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
            pPis: (float) ($item->aliq_pis_icms ?? 0),
            vBcPis: round((float) ($item->base_pis_icms ?? 0), 2),
            cstPis: self::optionalDigits((string) ($item->cst_pis ?? ''), 2),
            pCofins: (float) ($item->aliq_cofins_icms ?? 0),
            vBcCofins: round((float) ($item->base_cofins_icms ?? 0), 2),
            cstCofins: self::optionalDigits((string) ($item->cst_cofins ?? ''), 2),
            vIpi: round((float) ($item->valor_ipi ?? 0), 2),
            pIpi: (float) ($item->aliq_ipi ?? 0),
            vBcIpi: round((float) ($item->base_ipi ?? 0), 2),
            cstIpi: self::optionalDigits((string) ($item->cst_ipi ?? ''), 2),
            vIcmsDeson: round((float) ($item->valor_desoneracao ?? 0), 2),
            motivoDesoneracao: filled($item->motivo_desoneracao) ? (string) $item->motivo_desoneracao : null,
            crt: $crt,
            pRedBc: (float) ($item->p_red_bc_icms ?? 0),
            modBc: filled($item->mod_bc_icms) ? (string) $item->mod_bc_icms : null,
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
        $vBc = round($base, 2);

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
            $vBcIbscbs = $vBc;
            $vIbsUf = self::calcValor($vBc, $pIbsUf, $pRedIbs);
            $vIbsMun = self::calcValor($vBc, $pIbsMun, $pRedIbs);
            $vCbs = self::calcValor($vBc, $pCbs, $pRedCbs);
        }

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosn,
            vTotTrib: $vTotTrib,
            cstIbsCbs: $cstIbs !== null && $cstIbs !== '' ? $cstIbs : null,
            cClassTrib: $cClass !== null && $cClass !== '' ? $cClass : null,
            vBcIbscbs: $vBcIbscbs,
            pIbsUf: $pIbsUf,
            vIbsUf: $vIbsUf,
            pIbsMun: $pIbsMun,
            vIbsMun: $vIbsMun,
            pCbs: $pCbs,
            vCbs: $vCbs,
            pRedIbs: $pRedIbs,
            pRedCbs: $pRedCbs,
            crt: $crt,
        );
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
}
