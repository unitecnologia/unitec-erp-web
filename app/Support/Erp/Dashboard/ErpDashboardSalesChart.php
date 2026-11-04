<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
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
    public static function data(?Carbon $from = null, ?Carbon $to = null, int|array|null $empresaScope = null): array
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
            $empresaScope,
        );

        return [
            'defaultFrom' => $from->toDateString(),
            'defaultTo' => $to->toDateString(),
            'points' => $dbPoints,
        ];
    }

    /**
     * @return list<array{date: string, label: string, value: float}>
     */
    private static function pointsFromDatabase(Carbon $from, Carbon $to, int|array|null $empresaScope = null): array
    {
        $byDay = [];

        try {
            if (ErpSchema::hasTable((new Venda)->getTable())) {
                $q = Venda::query()
                    ->whereNotIn('status', [Venda::STATUS_CANCELADO]);

                ErpDashboardDateFilter::whereDateColumnBetween($q, 'data', $from, $to);
                ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), $empresaScope);

                // data é DATE: agrupa na coluna (sem DATE()) para aproveitar índice.
                $rows = $q
                    ->selectRaw('`data` as dia, SUM(`total`) as total')
                    ->groupBy('data')
                    ->orderBy('data')
                    ->get();

                foreach ($rows as $row) {
                    $dia = static::normalizeDay((string) ($row->dia ?? ''));
                    if ($dia === '') {
                        continue;
                    }
                    $byDay[$dia] = ($byDay[$dia] ?? 0.0) + (float) $row->total;
                }
            }

            if (ErpSchema::hasTable((new PdvVenda)->getTable())) {
                $q = PdvVenda::query()
                    ->where('situacao', '!=', 'C')
                    ->whereNull('venda_id');

                ErpDashboardDateFilter::wherePdvPeriodo($q, $from, $to);
                ErpEmpresaScopeFilter::applyPdvSessao($q, $empresaScope);

                $rows = $q
                    ->selectRaw('DATE(COALESCE(fechado_em, created_at)) as dia, SUM(`total`) as total')
                    ->groupBy('dia')
                    ->orderBy('dia')
                    ->get();

                foreach ($rows as $row) {
                    $dia = static::normalizeDay((string) ($row->dia ?? ''));
                    if ($dia === '') {
                        continue;
                    }
                    $byDay[$dia] = ($byDay[$dia] ?? 0.0) + (float) $row->total;
                }
            }
        } catch (Throwable) {
            return [];
        }

        if ($byDay === []) {
            return [];
        }

        ksort($byDay);

        $points = [];
        foreach ($byDay as $dia => $total) {
            $date = Carbon::parse($dia);
            $points[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'value' => round((float) $total, 2),
            ];
        }

        return $points;
    }

    private static function normalizeDay(string $dia): string
    {
        if ($dia === '') {
            return '';
        }

        return strlen($dia) >= 10 ? substr($dia, 0, 10) : $dia;
    }
}
