<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardCashflowChart
{
    /**
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>}
     */
    public static function data(): array
    {
        $real = static::fromDatabase();

        if ($real !== null) {
            return $real;
        }

        return ErpDashboardDemoData::cashflowChart();
    }

    /**
     * @return array{labels: list<string>, entradas: list<float>, saidas: list<float>}|null
     */
    private static function fromDatabase(): ?array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())
                && ! Schema::hasTable((new ContaPagar)->getTable())) {
                return null;
            }

            $labels = [];
            $entradas = [];
            $saidas = [];

            for ($week = 3; $week >= 0; $week--) {
                $inicio = Carbon::today()->startOfWeek()->subWeeks($week);
                $fim = $inicio->copy()->endOfWeek();

                $labels[] = 'Sem ' . (4 - $week);
                $entradas[] = static::sumRecebimentos($inicio, $fim);
                $saidas[] = static::sumPagamentos($inicio, $fim);
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

    private static function sumRecebimentos(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasTable((new ContaReceber)->getTable())) {
            return 0.0;
        }

        return (float) ContaReceber::query()
            ->whereNotNull('recebido_em')
            ->whereDate('recebido_em', '>=', $from->toDateString())
            ->whereDate('recebido_em', '<=', $to->toDateString())
            ->sum('valor_recebido');
    }

    private static function sumPagamentos(Carbon $from, Carbon $to): float
    {
        if (! Schema::hasTable((new ContaPagar)->getTable())) {
            return 0.0;
        }

        return (float) ContaPagar::query()
            ->whereNotNull('pago_em')
            ->whereDate('pago_em', '>=', $from->toDateString())
            ->whereDate('pago_em', '<=', $to->toDateString())
            ->sum('valor_pago');
    }
}
