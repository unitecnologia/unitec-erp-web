<?php

namespace App\Support\Erp;

class ProductPriceCalculator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function recalculateFromCompra(array $data): array
    {
        $compra = BrDecimal::parse($data['preco_compra'] ?? 0, 2);
        $pctCustos = BrDecimal::parse($data['pct_custos'] ?? 0, 2);
        $margem = BrDecimal::parse($data['pct_lucro'] ?? 0, 2);

        $custo = round($compra + ($compra * $pctCustos / 100), 2);
        $data['preco_custo'] = $custo;
        $data['preco_venda'] = round($custo + ($custo * $margem / 100), 2);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function recalculateFromCusto(array $data): array
    {
        $compra = BrDecimal::parse($data['preco_compra'] ?? 0, 2);
        $custo = BrDecimal::parse($data['preco_custo'] ?? 0, 2);
        $margem = BrDecimal::parse($data['pct_lucro'] ?? 0, 2);

        if ($compra > 0) {
            $pctCustos = round((($custo * 100) / $compra) - 100, 2);
            $data['pct_custos'] = max(0, $pctCustos);
        }

        $data['preco_venda'] = round($custo + ($custo * $margem / 100), 2);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function recalculateFromMargem(array $data): array
    {
        $custo = BrDecimal::parse($data['preco_custo'] ?? 0, 2);
        $margem = BrDecimal::parse($data['pct_lucro'] ?? 0, 2);

        $data['preco_venda'] = round($custo + ($custo * $margem / 100), 2);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function recalculateFromVenda(array $data): array
    {
        $custo = BrDecimal::parse($data['preco_custo'] ?? 0, 2);
        $venda = BrDecimal::parse($data['preco_venda'] ?? 0, 2);

        if ($custo > 0) {
            $margem = round((($venda * 100) / $custo) - 100, 2);
            $data['pct_lucro'] = max(0, $margem);
        } else {
            $data['pct_lucro'] = 0;
        }

        return $data;
    }
}
