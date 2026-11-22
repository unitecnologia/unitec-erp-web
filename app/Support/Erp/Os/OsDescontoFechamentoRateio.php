<?php

namespace App\Support\Erp\Os;

use App\Support\Erp\ErpMoney;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;

/**
 * Desconto de fechamento F4 da OS → rateio proporcional nos itens.
 * Mesma regra da Tela de Venda ({@see ForcaVendasMargemVendaCalculator::ratearValorNosItens}):
 * base = totais líquidos das linhas (já com desconto individual); centavos no último elegível.
 * Não altera vl_desc_* (desconto global do formulário).
 */
final class OsDescontoFechamentoRateio
{
    /**
     * @param  list<array<string, mixed>>  $itens
     * @return array{itens: list<array<string, mixed>>, desconto_aplicado: float}
     */
    public static function aplicar(array $itens, float $descontoCabecalho): array
    {
        $descontoCabecalho = round(max(0, $descontoCabecalho), 2);

        if ($descontoCabecalho <= 0 || $itens === []) {
            return ['itens' => $itens, 'desconto_aplicado' => 0.0];
        }

        $indices = [];
        $bases = [];

        foreach ($itens as $index => $row) {
            $total = self::money($row['total'] ?? 0);

            if ($total <= 0) {
                continue;
            }

            $indices[] = $index;
            $bases[] = ['total' => $total];
        }

        if ($bases === []) {
            return ['itens' => $itens, 'desconto_aplicado' => 0.0];
        }

        $base = round(array_sum(array_column($bases, 'total')), 2);
        $desconto = min($descontoCabecalho, $base);
        $rateio = ForcaVendasMargemVendaCalculator::ratearValorNosItens($desconto, $base, $bases);

        foreach ($rateio as $ei => $parte) {
            $parte = round((float) $parte, 2);

            if ($parte <= 0) {
                continue;
            }

            $index = $indices[$ei];
            $descAtual = self::money($itens[$index]['desconto'] ?? 0);
            $itens[$index]['desconto'] = ErpMoney::formatBr(round($descAtual + $parte, 2));
            $itens[$index] = self::recalcRow($itens[$index]);
        }

        return [
            'itens' => $itens,
            'desconto_aplicado' => $desconto,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function recalcRow(array $row): array
    {
        $qtd = max(0, ErpMoney::parseBr($row['qtd'] ?? 0, 3));
        $preco = max(0, ErpMoney::parseBr($row['preco'] ?? 0));
        $acrescimo = max(0, ErpMoney::parseBr($row['acrescimo'] ?? 0));
        $desconto = max(0, ErpMoney::parseBr($row['desconto'] ?? 0));
        $bruto = round($qtd * $preco, 2);
        $total = round(max(0, $bruto + $acrescimo - $desconto), 2);

        $row['qtd'] = ErpMoney::formatBr($qtd, 3);
        $row['preco'] = ErpMoney::formatBr($preco);
        $row['acrescimo'] = ErpMoney::formatBr($acrescimo);
        $row['desconto'] = ErpMoney::formatBr($desconto);
        $row['total'] = ErpMoney::formatBr($total);

        return $row;
    }

    private static function money(mixed $value): float
    {
        return max(0, ErpMoney::parseBr($value));
    }
}
