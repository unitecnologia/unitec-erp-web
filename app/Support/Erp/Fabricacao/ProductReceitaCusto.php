<?php

namespace App\Support\Erp\Fabricacao;

use App\Models\Product;

/**
 * Custo unitário para estimativa de receita — nunca usa preço de venda.
 */
final class ProductReceitaCusto
{
    public static function unitario(Product $product): float
    {
        $custo = round((float) ($product->preco_custo ?? 0), 2);

        if ($custo > 0) {
            return $custo;
        }

        return round((float) ($product->preco_compra ?? 0), 2);
    }

    /**
     * @param  list<array{quantidade: float, custo_unitario: float}>  $linhas
     * @return array{custo_total: float, custo_unitario_produzido: float}
     */
    public static function estimar(float $qtdProduzida, array $linhas): array
    {
        $total = 0.0;

        foreach ($linhas as $linha) {
            $qtd = (float) ($linha['quantidade'] ?? 0);
            $custo = (float) ($linha['custo_unitario'] ?? 0);
            $total += round($qtd * $custo, 2);
        }

        $total = round($total, 2);
        $unitario = $qtdProduzida > 0.0000001
            ? round($total / $qtdProduzida, 2)
            : 0.0;

        return [
            'custo_total' => $total,
            'custo_unitario_produzido' => $unitario,
        ];
    }
}
