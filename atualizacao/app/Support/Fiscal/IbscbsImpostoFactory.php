<?php

namespace App\Support\Fiscal;

use App\Models\NfeItem;
use App\Models\Product;
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
    ): ItemImpostoDto {
        $product = $item->product;
        $cst = self::digits((string) ($item->cst ?? ''), 2);
        $csosnNorm = self::digits($csosn, 3) ?: self::digits((string) ($item->csosn ?? ''), 3) ?: '102';

        $vBcIcms = round((float) ($item->base_icms ?? 0), 2);
        $pIcms = round((float) ($item->aliq_icms ?? 0), 2);
        $vIcmsItem = round((float) ($item->valor_icms ?? $vIcms), 2);
        $vBcComum = $vBcIcms > 0 ? $vBcIcms : round((float) ($item->total ?? 0), 2);

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosnNorm,
            vBc: $vBcIcms,
            vIcms: $vIcmsItem,
            vPis: round((float) ($item->valor_pis_icms ?? 0), 2),
            vCofins: round((float) ($item->valor_cofins_icms ?? 0), 2),
            vTotTrib: $vTotTrib,
            cstIbsCbs: self::optionalDigits((string) ($item->cst_ibs_cbs ?: $product?->iva_cst ?: ''), 3),
            cClassTrib: self::optionalDigits((string) ($item->class_trib ?: $product?->cclass_trib ?: ''), 6),
            vBcIbscbs: round((float) ($item->bc_ibs ?: $item->total ?: 0), 2),
            pIbsUf: (float) ($item->alq_ibs_uf ?: $product?->aliq_ibs_uf ?: 0),
            vIbsUf: self::resolveValor($item->v_ibs_uf, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_ibs_uf ?: $product?->aliq_ibs_uf ?: 0), (float) ($product?->reducao_ibs ?? 0)),
            pIbsMun: (float) ($item->alq_ibs_mun ?: $product?->aliq_ibs_mun ?: 0),
            vIbsMun: self::resolveValor($item->v_ibs_mun, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_ibs_mun ?: $product?->aliq_ibs_mun ?: 0), (float) ($product?->reducao_ibs ?? 0)),
            pCbs: (float) ($item->alq_cbs ?: $product?->aliq_cbs ?: 0),
            vCbs: self::resolveValor($item->v_cbs, (float) ($item->bc_ibs ?: $item->total ?: 0), (float) ($item->alq_cbs ?: $product?->aliq_cbs ?: 0), (float) ($product?->reducao_cbs ?? 0)),
            pRedIbs: (float) ($product?->reducao_ibs ?? 0),
            pRedCbs: (float) ($product?->reducao_cbs ?? 0),
            cstIcms: $cst !== '' ? $cst : null,
            pIcms: $pIcms,
            pPis: round((float) ($item->aliq_pis_icms ?? 0), 2),
            vBcPis: round((float) ($item->base_pis_icms ?: $vBcComum), 2),
            cstPis: self::normalizeCst((string) ($item->cst_pis ?? '01')),
            pCofins: round((float) ($item->aliq_cofins_icms ?? 0), 2),
            vBcCofins: round((float) ($item->base_cofins_icms ?: $vBcComum), 2),
            cstCofins: self::normalizeCst((string) ($item->cst_cofins ?? '01')),
            vIpi: round((float) ($item->valor_ipi ?? 0), 2),
            pIpi: round((float) ($item->aliq_ipi ?? 0), 2),
            vBcIpi: round((float) ($item->base_ipi ?: $vBcComum), 2),
            cstIpi: self::normalizeCst((string) ($item->cst_ipi ?? '99')),
            vIcmsDeson: round((float) ($item->valor_desoneracao ?? 0), 2),
            motivoDesoneracao: filled($item->motivo_desoneracao) ? (string) $item->motivo_desoneracao : null,
            crt: $crt,
        );
    }

    public static function fromProduct(?Product $product, float $base, float $vTotTrib = 0.0): ItemImpostoDto
    {
        $origem = (int) ($product?->origem ?? 0);
        $csosn = (string) ($product?->csosn ?: '102');
        $cst = self::digits((string) ($product?->cst_icms ?? ''), 2);
        $cstIbs = self::digits((string) ($product?->iva_cst ?? ''), 3);
        $cClass = self::digits((string) ($product?->cclass_trib ?? ''), 6);

        $pIbsUf = (float) ($product?->aliq_ibs_uf ?? 0);
        $pIbsMun = (float) ($product?->aliq_ibs_mun ?? 0);
        $pCbs = (float) ($product?->aliq_cbs ?? 0);
        $pRedIbs = (float) ($product?->reducao_ibs ?? 0);
        $pRedCbs = (float) ($product?->reducao_cbs ?? 0);
        $vBc = round($base, 2);

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosn,
            vTotTrib: $vTotTrib,
            cstIbsCbs: $cstIbs !== '' ? $cstIbs : null,
            cClassTrib: $cClass !== '' ? $cClass : null,
            vBcIbscbs: $vBc,
            pIbsUf: $pIbsUf,
            vIbsUf: self::calcValor($vBc, $pIbsUf, $pRedIbs),
            pIbsMun: $pIbsMun,
            vIbsMun: self::calcValor($vBc, $pIbsMun, $pRedIbs),
            pCbs: $pCbs,
            vCbs: self::calcValor($vBc, $pCbs, $pRedCbs),
            pRedIbs: $pRedIbs,
            pRedCbs: $pRedCbs,
            cstIcms: $cst !== '' ? $cst : null,
            cstPis: self::normalizeCst((string) ($product?->cst_saida ?? '01')),
            cstCofins: self::normalizeCst((string) ($product?->cst_cofins ?? $product?->cst_saida ?? '01')),
            cstIpi: self::normalizeCst((string) ($product?->cst_ipi ?? '99')),
        );
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

    private static function normalizeCst(string $value): string
    {
        return self::digits($value, 2) ?: '01';
    }
}
