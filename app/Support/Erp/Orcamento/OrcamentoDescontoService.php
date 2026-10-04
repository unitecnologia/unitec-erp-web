<?php

namespace App\Support\Erp\Orcamento;

use App\Models\OrcamentoItem;

/**
 * Separa desconto do item e desconto geral.
 * Não grava o desconto geral em orcamento_itens.desconto.
 */
final class OrcamentoDescontoService
{
    /**
     * Desconto próprio da linha e acréscimo reconstruído pelo total gravado:
     * acréscimo = max(0, total − (quantidade × preço − desconto do item)).
     *
     * @return array{desconto: float, acrescimo: float, total: float}
     */
    public function partesDaLinha(OrcamentoItem $item): array
    {
        $bruto = round((float) $item->quantidade * (float) $item->preco_unitario, 2);
        $total = round(max(0, (float) $item->total), 2);
        $desconto = round(max(0, (float) $item->desconto), 2);

        return [
            'desconto' => $desconto,
            'acrescimo' => round(max(0, $total - ($bruto - $desconto)), 2),
            'total' => $total,
        ];
    }

    /**
     * Cota do desconto geral para a NF-e, no mesmo critério do rateio do
     * cabeçalho da nota: peso = quantidade × preço, resto no último item.
     * Não altera o orçamento.
     *
     * @param  iterable<int, OrcamentoItem>  $itens
     * @return array<int, float>
     */
    public function cotasDescontoGeral(iterable $itens, float $descontoGeral): array
    {
        $itens = array_values(iterator_to_array($itens));
        $rateio = array_fill(0, count($itens), 0.0);
        $descontoGeral = round(max(0, $descontoGeral), 2);

        if ($descontoGeral <= 0 || $itens === []) {
            return $rateio;
        }

        $pesos = [];
        $somaPesos = 0.0;

        foreach ($itens as $index => $item) {
            $peso = max(0.0, round((float) $item->quantidade * (float) $item->preco_unitario, 2));
            $pesos[$index] = $peso;
            $somaPesos += $peso;
        }

        $lastIndex = array_key_last($itens);
        $restante = $descontoGeral;

        if ($somaPesos <= 0) {
            $count = count($itens);

            foreach ($itens as $index => $item) {
                if ($index === $lastIndex) {
                    $rateio[$index] = $restante;
                } else {
                    $parte = round($descontoGeral / $count, 2);
                    $rateio[$index] = $parte;
                    $restante = round($restante - $parte, 2);
                }
            }

            return $rateio;
        }

        foreach ($itens as $index => $item) {
            if ($index === $lastIndex) {
                $rateio[$index] = $restante;

                continue;
            }

            $parte = round($descontoGeral * ($pesos[$index] / $somaPesos), 2);
            $rateio[$index] = $parte;
            $restante = round($restante - $parte, 2);
        }

        return $rateio;
    }
}
