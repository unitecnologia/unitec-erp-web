<?php

namespace App\Support\Erp\Reports\LucratividadePorVenda;

use App\Support\Erp\Tef\TefMoney;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;

/**
 * Rateia a diferença entre vendas.total e a soma dos itens (desconto/acréscimo de cabeçalho).
 * Mesma regra proporcional da Tela de Venda (sobra no último elegível).
 */
final class LucratividadeVendaReceitaRateio
{
    /**
     * @param  list<float|int|string>  $totaisItens  totais brutos das linhas (venda_itens.total)
     * @return list<string> totais já rateados (string money), na mesma ordem
     */
    public static function totaisRateados(float|string $vendaTotal, array $totaisItens): array
    {
        $n = count($totaisItens);
        if ($n === 0) {
            return [];
        }

        $alvo = TefMoney::normalize($vendaTotal);
        $bases = [];
        $soma = '0.00';

        foreach ($totaisItens as $total) {
            $t = TefMoney::normalize($total);
            // Base do rateio usa valor absoluto positivo; linhas zeradas não recebem ajuste.
            $baseLinha = max(0.0, (float) $t);
            $bases[] = ['total' => $baseLinha];
            $soma = TefMoney::add($soma, $t);
        }

        $diff = round((float) $alvo - (float) $soma, 2);
        if (abs($diff) < 0.005) {
            return array_map(static fn ($t): string => TefMoney::normalize($t), $totaisItens);
        }

        $basePositiva = round(array_sum(array_column($bases, 'total')), 2);
        if ($basePositiva <= 0) {
            // Sem base: mantém itens e não inventa rateio.
            return array_map(static fn ($t): string => TefMoney::normalize($t), $totaisItens);
        }

        $out = array_map(static fn ($t): string => TefMoney::normalize($t), $totaisItens);

        if ($diff < 0) {
            // Desconto de cabeçalho: abate proporcionalmente.
            $desconto = min(abs($diff), $basePositiva);
            $partes = ForcaVendasMargemVendaCalculator::ratearValorNosItens($desconto, $basePositiva, $bases);
            foreach ($partes as $i => $parte) {
                $out[$i] = TefMoney::sub($out[$i], TefMoney::normalize($parte));
                if (TefMoney::cmp($out[$i], '0') === -1) {
                    $out[$i] = '0.00';
                }
            }

            return $out;
        }

        // Acréscimo de cabeçalho: soma proporcionalmente.
        $partes = ForcaVendasMargemVendaCalculator::ratearValorNosItens($diff, $basePositiva, $bases);
        foreach ($partes as $i => $parte) {
            $out[$i] = TefMoney::add($out[$i], TefMoney::normalize($parte));
        }

        return $out;
    }
}
