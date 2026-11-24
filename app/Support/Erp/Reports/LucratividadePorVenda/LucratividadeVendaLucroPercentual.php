<?php

namespace App\Support\Erp\Reports\LucratividadePorVenda;

/**
 * % lucro sobre o custo histórico (D2.6) — compartilhado entre
 * Lucratividade por Venda e Relatório Geral (% LUCRO).
 *
 * Fórmula: ((venda líquida − custo histórico) / custo histórico) × 100
 * Ex.: venda 100, custo 50 → 100%.
 *
 * Diferente da Margem da Tela/Pedido ({@see \App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator}),
 * que calcula sobre a venda ((lucro / venda) × 100).
 */
final class LucratividadeVendaLucroPercentual
{
    /**
     * @return float|null null quando o custo não permite divisão (≤ 0)
     */
    public static function sobreCusto(float|string $vendaLiquida, float|string $custoHistorico): ?float
    {
        $venda = round((float) $vendaLiquida, 2);
        $custo = round((float) $custoHistorico, 2);

        if ($custo <= 0.0000001) {
            return null;
        }

        $lucro = round($venda - $custo, 2);

        return round(($lucro / $custo) * 100, 2);
    }

    public static function rotulo(float|string $vendaLiquida, float|string $custoHistorico): string
    {
        $pct = self::sobreCusto($vendaLiquida, $custoHistorico);

        if ($pct === null) {
            return LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
        }

        return \App\Support\Erp\Reports\Tabular\AbstractTabularReport::formatPercent($pct);
    }
}
