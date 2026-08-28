<?php

namespace App\Support\Erp\Dashboard;

use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardSalesMetrics
{
    /**
     * @param  int|list<int>|null  $empresaScope
     */
    public static function faturamentoDia(?Carbon $day = null, int|array|null $empresaScope = null): float
    {
        $day ??= Carbon::today();

        return round(
            static::sumVendasNaData($day, $empresaScope) + static::sumPdvSemEspelhoNaData($day, $empresaScope),
            2
        );
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    public static function faturamentoPeriodo(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        return round(
            static::sumVendasNoPeriodo($from, $to, $empresaScope) + static::sumPdvSemEspelhoNoPeriodo($from, $to, $empresaScope),
            2
        );
    }

    public static function variacaoPercentual(float $atual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    public static function hintVariacaoDia(float $hoje, float $ontem): string
    {
        $variacao = static::variacaoPercentual($hoje, $ontem);

        if ($variacao !== null) {
            $sinal = $variacao > 0 ? '+' : '';

            return $sinal.number_format($variacao, 1, ',', '').'% vs ontem';
        }

        if ($hoje > 0 && $ontem <= 0) {
            return 'Sem vendas ontem';
        }

        return 'Sem vendas no período';
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumVendasNaData(Carbon $day, int|array|null $empresaScope = null): float
    {
        try {
            if (! Schema::hasTable((new Venda)->getTable())) {
                return 0.0;
            }

            $q = Venda::query()
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->whereDate('data', $day->toDateString());

            ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), $empresaScope);

            return (float) $q->sum('total');
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumVendasNoPeriodo(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        try {
            if (! Schema::hasTable((new Venda)->getTable())) {
                return 0.0;
            }

            $q = Venda::query()
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->whereDate('data', '>=', $from->toDateString())
                ->whereDate('data', '<=', $to->toDateString());

            ErpEmpresaScopeFilter::applyColumn($q, (new Venda)->getTable(), $empresaScope);

            return (float) $q->sum('total');
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumPdvSemEspelhoNaData(Carbon $day, int|array|null $empresaScope = null): float
    {
        try {
            if (! Schema::hasTable((new PdvVenda)->getTable())) {
                return 0.0;
            }

            $q = PdvVenda::query()
                ->where('situacao', '!=', 'C')
                ->whereNull('venda_id')
                ->where(function ($query) use ($day): void {
                    $query->whereDate('fechado_em', $day->toDateString())
                        ->orWhere(function ($fallback) use ($day): void {
                            $fallback->whereNull('fechado_em')
                                ->whereDate('created_at', $day->toDateString());
                        });
                });

            ErpEmpresaScopeFilter::applyPdvSessao($q, $empresaScope);

            return (float) $q->sum('total');
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function sumPdvSemEspelhoNoPeriodo(Carbon $from, Carbon $to, int|array|null $empresaScope = null): float
    {
        try {
            if (! Schema::hasTable((new PdvVenda)->getTable())) {
                return 0.0;
            }

            $q = PdvVenda::query()
                ->where('situacao', '!=', 'C')
                ->whereNull('venda_id')
                ->where(function ($query) use ($from, $to): void {
                    $query->where(function ($fechamento) use ($from, $to): void {
                        $fechamento->whereNotNull('fechado_em')
                            ->whereDate('fechado_em', '>=', $from->toDateString())
                            ->whereDate('fechado_em', '<=', $to->toDateString());
                    })->orWhere(function ($fallback) use ($from, $to): void {
                        $fallback->whereNull('fechado_em')
                            ->whereDate('created_at', '>=', $from->toDateString())
                            ->whereDate('created_at', '<=', $to->toDateString());
                    });
                });

            ErpEmpresaScopeFilter::applyPdvSessao($q, $empresaScope);

            return (float) $q->sum('total');
        } catch (Throwable) {
            return 0.0;
        }
    }
}
