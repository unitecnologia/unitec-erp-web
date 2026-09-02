<?php

namespace App\Support\Erp\Dashboard;

use App\Models\CaixaLancamento;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use App\Support\Erp\ErpSchema;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fluxo de caixa do dashboard ERP — mesma base do Contas Caixa / Executivo:
 * caixa_lancamentos (entrada/saída por emissão).
 */
final class ErpDashboardCashflowChart
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>, empty?: bool}
     */
    public static function data(int|array|null $empresaScope = null): array
    {
        return static::fromDatabase($empresaScope) ?? [
            'labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
            'entradas' => [0.0, 0.0, 0.0, 0.0],
            'saidas' => [0.0, 0.0, 0.0, 0.0],
            'empty' => true,
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>, empty?: bool}|null
     */
    private static function fromDatabase(int|array|null $empresaScope = null): ?array
    {
        try {
            $collector = ErpDashboardCollector::current();

            if ($collector !== null) {
                $semanas = $collector->cashflowSemanas();
                $totais = $collector->cashflowEntradasSaidas();
            } else {
                if (! ErpSchema::hasTable((new CaixaLancamento)->getTable())) {
                    return null;
                }

                $hoje = ErpFinanceiroMetricas::hoje();
                $semanas = [];
                for ($week = 3; $week >= 0; $week--) {
                    $inicio = $hoje->copy()->startOfWeek()->subWeeks($week)->startOfDay();
                    $fim = $inicio->copy()->endOfWeek()->endOfDay();
                    if ($week === 0) {
                        $fim = $fim->min($hoje->copy()->endOfDay());
                    }
                    $semanas[] = ['inicio' => $inicio, 'fim' => $fim];
                }

                $totais = ErpFinanceiroMetricas::entradasSaidasPorSemanas($semanas, $empresaScope);
            }

            $labels = [];
            $entradas = [];
            $saidas = [];

            foreach ($semanas as $i => $_) {
                $labels[] = 'Sem '.($i + 1);
                $entradas[] = (float) ($totais[$i]['entradas'] ?? 0);
                $saidas[] = (float) ($totais[$i]['saidas'] ?? 0);
            }

            if (array_sum($entradas) <= 0 && array_sum($saidas) <= 0) {
                return [
                    'labels' => $labels,
                    'entradas' => $entradas,
                    'saidas' => $saidas,
                    'empty' => true,
                ];
            }

            return [
                'labels' => $labels,
                'entradas' => $entradas,
                'saidas' => $saidas,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
