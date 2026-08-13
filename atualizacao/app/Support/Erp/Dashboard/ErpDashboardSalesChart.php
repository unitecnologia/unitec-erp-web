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
     * Período padrão: do primeiro dia do mês até hoje.
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
        $to ??= $hoje->copy();

        // Nunca mostrar dados futuros para o cliente, mesmo se uma data
        // indevida tiver sido gravada na base.
        $to = $to->min($hoje);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $dbPoints = static::pointsFromDatabase(
            $from->copy()->startOfMonth()->subMonthNoOverflow(),
            $to,
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
            'points' => [],
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
