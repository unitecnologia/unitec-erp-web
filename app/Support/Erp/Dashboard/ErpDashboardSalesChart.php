<?php

namespace App\Support\Erp\Dashboard;

use App\Models\Venda;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ErpDashboardSalesChart
{
    /**
     * Período padrão: mês civil corrente (1º dia → último dia).
     * Ex.: em julho/2026 → 01/07/2026 a 31/07/2026.
     *
     * @return array{
     *     defaultFrom: string,
     *     defaultTo: string,
     *     points: list<array{date: string, label: string, value: float}>
     * }
     */
    public static function data(?Carbon $from = null, ?Carbon $to = null): array
    {
        $hoje = ErpFinanceiroMetricas::hoje()->startOfDay();
        $from ??= $hoje->copy()->startOfMonth();
        $to ??= $hoje->copy()->endOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // Busca um pouco além do filtro padrão para o usuário poder ampliar o período.
        $dbPoints = static::pointsFromDatabase(
            $from->copy()->startOfMonth()->subMonthNoOverflow(),
            $to->copy()->endOfMonth(),
        );

        if ($dbPoints !== []) {
            return [
                'defaultFrom' => $from->toDateString(),
                'defaultTo' => $to->toDateString(),
                'points' => $dbPoints,
            ];
        }

        return [
            'defaultFrom' => $from->toDateString(),
            'defaultTo' => $to->toDateString(),
            'points' => ErpDashboardDemoData::salesChartPoints(),
        ];
    }

    /**
     * @return list<array{date: string, label: string, value: float}>
     */
    private static function pointsFromDatabase(Carbon $from, Carbon $to): array
    {
        try {
            if (! Schema::hasTable((new Venda)->getTable())) {
                return [];
            }

            $rows = Venda::query()
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->whereDate('data', '>=', $from->toDateString())
                ->whereDate('data', '<=', $to->toDateString())
                ->selectRaw('DATE(`data`) as dia, SUM(`total`) as total')
                ->groupBy('dia')
                ->orderBy('dia')
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            return $rows->map(function ($row): array {
                $date = Carbon::parse($row->dia);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('d/m'),
                    'value' => (float) $row->total,
                ];
            })->values()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
