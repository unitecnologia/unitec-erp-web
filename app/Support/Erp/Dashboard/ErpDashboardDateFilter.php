<?php

namespace App\Support\Erp\Dashboard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Filtros de período sargáveis para o Dashboard.
 *
 * Colunas DATE: comparação direta (evita DATE(coluna) do whereDate).
 * Colunas DATETIME/TIMESTAMP: intervalo meio-aberto [início do dia, início do dia seguinte).
 */
final class ErpDashboardDateFilter
{
    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateColumn(Builder|QueryBuilder $query, string $column, Carbon $day): void
    {
        $query->where($column, $day->toDateString());
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateColumnBetween(Builder|QueryBuilder $query, string $column, Carbon $from, Carbon $to): void
    {
        $query->where($column, '>=', $from->toDateString())
            ->where($column, '<=', $to->toDateString());
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateColumnLte(Builder|QueryBuilder $query, string $column, Carbon $day): void
    {
        $query->where($column, '<=', $day->toDateString());
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateColumnLt(Builder|QueryBuilder $query, string $column, Carbon $day): void
    {
        $query->where($column, '<', $day->toDateString());
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateColumnGte(Builder|QueryBuilder $query, string $column, Carbon $day): void
    {
        $query->where($column, '>=', $day->toDateString());
    }

    /**
     * Limites inclusivos de calendário como datetime meio-aberto.
     *
     * @return array{0: string, 1: string} [fromInclusive, toExclusive]
     */
    public static function dateTimeDayBounds(Carbon $from, Carbon $to): array
    {
        return [
            $from->copy()->startOfDay()->format('Y-m-d H:i:s'),
            $to->copy()->startOfDay()->addDay()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function whereDateTimeColumnBetween(Builder|QueryBuilder $query, string $column, Carbon $from, Carbon $to): void
    {
        [$start, $endExclusive] = self::dateTimeDayBounds($from, $to);
        $query->where($column, '>=', $start)
            ->where($column, '<', $endExclusive);
    }

    /**
     * PDV: fechado_em no período, ou created_at quando fechado_em é nulo.
     * Equivalente ao whereDate encadeado usado antes, sem DATE() na coluna.
     *
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function wherePdvPeriodo(Builder|QueryBuilder $query, Carbon $from, Carbon $to, string $tablePrefix = ''): void
    {
        $prefix = $tablePrefix !== '' ? rtrim($tablePrefix, '.').'.' : '';
        [$start, $endExclusive] = self::dateTimeDayBounds($from, $to);

        $query->where(function ($periodo) use ($prefix, $start, $endExclusive): void {
            $periodo->where(function ($fechamento) use ($prefix, $start, $endExclusive): void {
                $fechamento->whereNotNull($prefix.'fechado_em')
                    ->where($prefix.'fechado_em', '>=', $start)
                    ->where($prefix.'fechado_em', '<', $endExclusive);
            })->orWhere(function ($fallback) use ($prefix, $start, $endExclusive): void {
                $fallback->whereNull($prefix.'fechado_em')
                    ->where($prefix.'created_at', '>=', $start)
                    ->where($prefix.'created_at', '<', $endExclusive);
            });
        });
    }

    /**
     * Um único dia civil em colunas datetime (PDV).
     *
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function wherePdvDia(Builder|QueryBuilder $query, Carbon $day, string $tablePrefix = ''): void
    {
        self::wherePdvPeriodo($query, $day, $day, $tablePrefix);
    }
}
