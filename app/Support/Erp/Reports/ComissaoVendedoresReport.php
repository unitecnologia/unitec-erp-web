<?php

namespace App\Support\Erp\Reports;

use App\Models\Venda;

/**
 * Calcula a comissão dos vendedores a partir das vendas faturadas.
 *
 * Regra de negócio (definida pelo cliente):
 *   - base = valor faturado da venda (pela data da venda);
 *   - "a prazo" = boleto ou crediário (usa a alíquota comissao_ap);
 *   - todas as demais formas = "à vista" (usa a alíquota comissao_av).
 */
class ComissaoVendedoresReport
{
    public static function isAPrazo(?string $forma): bool
    {
        $f = mb_strtoupper((string) $forma, 'UTF-8');

        return str_contains($f, 'BOLETO') || str_contains($f, 'CREDI');
    }

    /**
     * Agrupa as vendas por vendedor e calcula os totais e comissões.
     *
     * @param  iterable<int, Venda>  $vendas
     * @return array{linhas: list<array<string, mixed>>, totais: array<string, float|int>}
     */
    public static function build(iterable $vendas): array
    {
        $grupos = [];

        foreach ($vendas as $venda) {
            $vid = $venda->vendedor_id ? (int) $venda->vendedor_id : 0;

            if (! isset($grupos[$vid])) {
                $grupos[$vid] = [
                    'vendedor_id' => $vid ?: null,
                    'nome' => $venda->vendedorNome() ?: 'LOJA',
                    'comissao_av' => (float) ($venda->vendedor?->comissao_av ?? 0),
                    'comissao_ap' => (float) ($venda->vendedor?->comissao_ap ?? 0),
                    'qtd' => 0,
                    'total_avista' => 0.0,
                    'total_aprazo' => 0.0,
                ];
            }

            $grupos[$vid]['qtd']++;

            if (self::isAPrazo($venda->forma_pagamento)) {
                $grupos[$vid]['total_aprazo'] += (float) $venda->total;
            } else {
                $grupos[$vid]['total_avista'] += (float) $venda->total;
            }
        }

        $linhas = [];
        $totais = [
            'qtd' => 0,
            'total_avista' => 0.0,
            'total_aprazo' => 0.0,
            'total_geral' => 0.0,
            'comissao_avista' => 0.0,
            'comissao_aprazo' => 0.0,
            'comissao_total' => 0.0,
        ];

        foreach ($grupos as $g) {
            $comAv = round($g['total_avista'] * $g['comissao_av'] / 100, 2);
            $comAp = round($g['total_aprazo'] * $g['comissao_ap'] / 100, 2);
            $totalGeral = round($g['total_avista'] + $g['total_aprazo'], 2);
            $comTotal = round($comAv + $comAp, 2);

            $linhas[] = [
                'vendedor_id' => $g['vendedor_id'],
                'nome' => $g['nome'],
                'comissao_av' => $g['comissao_av'],
                'comissao_ap' => $g['comissao_ap'],
                'qtd' => $g['qtd'],
                'total_avista' => round($g['total_avista'], 2),
                'total_aprazo' => round($g['total_aprazo'], 2),
                'total_geral' => $totalGeral,
                'comissao_avista' => $comAv,
                'comissao_aprazo' => $comAp,
                'comissao_total' => $comTotal,
            ];

            $totais['qtd'] += $g['qtd'];
            $totais['total_avista'] += $g['total_avista'];
            $totais['total_aprazo'] += $g['total_aprazo'];
            $totais['total_geral'] += $totalGeral;
            $totais['comissao_avista'] += $comAv;
            $totais['comissao_aprazo'] += $comAp;
            $totais['comissao_total'] += $comTotal;
        }

        usort($linhas, fn (array $a, array $b): int => strcmp((string) $a['nome'], (string) $b['nome']));

        foreach (['total_avista', 'total_aprazo', 'total_geral', 'comissao_avista', 'comissao_aprazo', 'comissao_total'] as $k) {
            $totais[$k] = round($totais[$k], 2);
        }

        return ['linhas' => $linhas, 'totais' => $totais];
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}
