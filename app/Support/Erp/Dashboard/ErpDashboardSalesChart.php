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
     * Período padrão: 1º dia do mês corrente até hoje
     * (ex.: 01/07 → 23/07; amanhã 01/07 → 24/07).
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
        $to ??= $hoje->copy();
        $from ??= $hoje->copy()->startOfMonth();

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

        $allDemo = ErpDashboardDemoData::salesChartPoints();
        $demoFrom = Carbon::parse($allDemo[0]['date'])->startOfDay();
        $demoTo = Carbon::parse($allDemo[array_key_last($allDemo)]['date'])->startOfDay();

        $defaultFrom = $from->lt($demoFrom) ? $demoFrom : $from;
        $defaultTo = $to->gt($demoTo) ? $demoTo : $to;

        if ($defaultFrom->gt($defaultTo)) {
            $defaultFrom = $demoFrom;
            $defaultTo = $demoTo;
        }

        return [
            'defaultFrom' => $defaultFrom->toDateString(),
            'defaultTo' => $defaultTo->toDateString(),
            'points' => $allDemo,
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
