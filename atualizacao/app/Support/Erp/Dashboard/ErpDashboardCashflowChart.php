<?php

namespace App\Support\Erp\Dashboard;

use App\Models\CaixaLancamento;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fluxo de caixa do dashboard ERP — mesma base do Contas Caixa / Executivo:
 * caixa_lancamentos (entrada/saída por emissão).
 */
final class ErpDashboardCashflowChart
{
    /**
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>}
     */
    public static function data(): array
    {
        return static::fromDatabase() ?? [
            'labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
            'entradas' => [0.0, 0.0, 0.0, 0.0],
            'saidas' => [0.0, 0.0, 0.0, 0.0],
        ];
    }

    /**
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>}|null
     */
    private static function fromDatabase(): ?array
    {
        try {
            if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
                return null;
            }

            $hoje = ErpFinanceiroMetricas::hoje();
            $labels = [];
            $entradas = [];
            $saidas = [];

            // Últimas 4 semanas (Sem 1 = mais antiga, Sem 4 = atual)
            for ($week = 3; $week >= 0; $week--) {
                $inicio = $hoje->copy()->startOfWeek()->subWeeks($week)->startOfDay();
                $fim = $inicio->copy()->endOfWeek()->endOfDay();

                // A semana atual termina hoje: nunca somar emissões futuras.
                if ($week === 0) {
                    $fim = $fim->min($hoje->copy()->endOfDay());
                }

                $labels[] = 'Sem '.(4 - $week);
                $entradas[] = ErpFinanceiroMetricas::sumCaixaCampo($inicio, $fim, 'entrada');
                $saidas[] = ErpFinanceiroMetricas::sumCaixaCampo($inicio, $fim, 'saida');
            }

            if (array_sum($entradas) <= 0 && array_sum($saidas) <= 0) {
                return null;
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
