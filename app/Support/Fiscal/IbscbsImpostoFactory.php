<?php

namespace App\Support\Fiscal;

use App\Models\NfeItem;
use App\Models\Product;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;

final class IbscbsImpostoFactory
{
    public static function fromNfeItem(NfeItem $item, int $origem, string $csosn, float $vIcms = 0.0, float $vTotTrib = 0.0): ItemImpostoDto
    {
        $product = $item->product;
        $cst = self::digits((string) ($item->cst_ibs_cbs ?: $product?->iva_cst ?: ''), 3);
        $cClass = self::digits((string) ($item->class_trib ?: $product?->cclass_trib ?: ''), 6);

        $vBc = (float) ($item->bc_ibs ?: $item->total ?: 0);
        $pIbsUf = (float) ($item->alq_ibs_uf ?: $product?->aliq_ibs_uf ?: 0);
        $pIbsMun = (float) ($item->alq_ibs_mun ?: $product?->aliq_ibs_mun ?: 0);
        $pCbs = (float) ($item->alq_cbs ?: $product?->aliq_cbs ?: 0);
        $pRedIbs = (float) ($product?->reducao_ibs ?? 0);
        $pRedCbs = (float) ($product?->reducao_cbs ?? 0);

        $vIbsUf = self::resolveValor($item->v_ibs_uf, $vBc, $pIbsUf, $pRedIbs);
        $vIbsMun = self::resolveValor($item->v_ibs_mun, $vBc, $pIbsMun, $pRedIbs);
        $vCbs = self::resolveValor($item->v_cbs, $vBc, $pCbs, $pRedCbs);

        return new ItemImpostoDto(
            origem: $origem,
            csosn: $csosn,
            vIcms: $vIcms,
            vTotTrib: $vTotTrib,
            cstIbsCbs: $cst !== '' ? $cst : null,
            cClassTrib: $cClass !== '' ? $cClass : null,
            vBcIbscbs: round($vBc, 2),
            pIbsUf: $pIbsUf,
            vIbsUf: $vIbsUf,
            pIbsMun: $pIbsMun,
            vIbsMun: $vIbsMun,
            pCbs: $pCbs,
            vCbs: $vCbs,
            pRedIbs: $pRedIbs,
            pRedCbs: $pRedCbs,
        );
    }

    public static function fromProduct(?Product $product, float $base, float $vTotTrib = 0.0): ItemImpostoDto
    {
        $origem = (int) ($product?->origem ?? 0);
        $csosn = (string) ($product?->csosn ?: '102');
        $cst = self::digits((string) ($product?->iva_cst ?? ''), 3);
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
            cstIbsCbs: $cst !== '' ? $cst : null,
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
}
