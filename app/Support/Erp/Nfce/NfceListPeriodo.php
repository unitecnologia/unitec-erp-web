<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Filtro de período da listagem NFC-e por fechamento da venda PDV.
 *
 * Com período informado, a query parte de {@see PdvVenda} (fechado_em sargável)
 * e faz STRAIGHT_JOIN em pdv_venda_nfce — evita full scan na tabela de XML.
 */
final class NfceListPeriodo
{
    public const JOIN_ALIAS = 'pv_filtro';

    /**
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     * @return Builder<\App\Models\PdvVendaNfce>
     */
    public static function aplicar(Builder $query, string $nfceTable, string $de, string $ate): Builder
    {
        $de = trim($de);
        $ate = trim($ate);

        if ($de === '' && $ate === '') {
            return $query;
        }

        self::forcarOrigemVendas($query, $nfceTable);

        $alias = self::JOIN_ALIAS;

        if ($de !== '') {
            $query->where("{$alias}.fechado_em", '>=', Carbon::parse($de)->startOfDay());
        }

        if ($ate !== '') {
            $query->where("{$alias}.fechado_em", '<', Carbon::parse($ate)->addDay()->startOfDay());
        }

        return $query;
    }

    /**
     * Soma totais das vendas PDV do filtro atual (reutiliza a origem vendas quando existir).
     * Usa COALESCE(SUM(alias.total)) no toBase — evita aggregate Eloquent frágil com FROM STRAIGHT_JOIN.
     *
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    public static function sumTotais(Builder $query, string $nfceTable): float
    {
        if (! self::temOrigemVendas($query) && ! self::jaTemJoin($query)) {
            self::garantirJoinVenda($query, $nfceTable);
        }

        $connection = $query->getConnection();
        $grammar = $connection->getQueryGrammar();
        $prefix = (string) $connection->getTablePrefix();
        $aliasSql = $prefix !== '' ? $prefix.self::JOIN_ALIAS : self::JOIN_ALIAS;
        $sumExpr = 'COALESCE(SUM('.$grammar->wrap($aliasSql).'.'.$grammar->wrap('total').'), 0)';

        $row = (clone $query)->toBase()
            ->cloneWithout(['columns', 'orders', 'groups', 'havings', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw($sumExpr.' as aggregate')
            ->first();

        return (float) ($row->aggregate ?? 0);
    }

    /**
     * Indica se o FROM já foi reescrito para partir de pdv_vendas.
     *
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    public static function temOrigemVendas(Builder $query): bool
    {
        $from = $query->getQuery()->from;

        if ($from instanceof Expression) {
            $sql = strtolower($query->getConnection()->getQueryGrammar()->getValue($from));

            return str_contains($sql, 'straight_join')
                && (str_contains($sql, strtolower(self::JOIN_ALIAS))
                    || str_contains($sql, strtolower($query->getConnection()->getTablePrefix().self::JOIN_ALIAS)));
        }

        return is_string($from)
            && str_contains(strtolower($from), 'straight_join');
    }

    /**
     * FROM pdv_vendas AS pv_filtro STRAIGHT_JOIN pdv_venda_nfce ON ...
     * Garante que o MySQL lidere pela venda (índice fechado_em) e faça eq_ref na NFC-e.
     *
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    protected static function forcarOrigemVendas(Builder $query, string $nfceTable): void
    {
        if (self::temOrigemVendas($query)) {
            return;
        }

        self::removerJoinVendaSeExistir($query);

        $connection = $query->getConnection();
        $grammar = $connection->getQueryGrammar();
        $prefix = (string) $connection->getTablePrefix();
        $vendasTable = (new PdvVenda)->getTable();
        $alias = self::JOIN_ALIAS;
        // where("pv_filtro.fechado_em") é compilado como `unitec_pv_filtro` — alias do FROM igual.
        $aliasSql = $prefix !== '' ? $prefix.$alias : $alias;

        // Colunas do ON precisam estar quotadas; senão o MySQL pode cair em ALL em vendas.
        $fromSql = sprintf(
            '%s as %s straight_join %s on %s.`pdv_venda_id` = %s.`id`',
            $grammar->wrapTable($vendasTable),
            $grammar->wrap($aliasSql),
            $grammar->wrapTable($nfceTable),
            $grammar->wrapTable($nfceTable),
            $grammar->wrap($aliasSql),
        );

        $query->from(DB::raw($fromSql));
    }

    /**
     * Fallback quando não há período (SUM ainda precisa do total da venda).
     *
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    protected static function garantirJoinVenda(Builder $query, string $nfceTable): void
    {
        if (self::temOrigemVendas($query) || self::jaTemJoin($query)) {
            return;
        }

        $vendasTable = (new PdvVenda)->getTable();
        $alias = self::JOIN_ALIAS;

        $query->join("{$vendasTable} as {$alias}", "{$alias}.id", '=', "{$nfceTable}.pdv_venda_id");
    }

    /**
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    protected static function removerJoinVendaSeExistir(Builder $query): void
    {
        $base = $query->getQuery();
        $joins = $base->joins ?? [];

        if ($joins === []) {
            return;
        }

        $alias = self::JOIN_ALIAS;
        $prefix = (string) $query->getConnection()->getTablePrefix();
        $prefixedAlias = $prefix.$alias;

        $base->joins = array_values(array_filter(
            $joins,
            static function ($join) use ($alias, $prefixedAlias): bool {
                if (! $join instanceof JoinClause) {
                    return true;
                }

                $table = (string) $join->table;

                return ! (
                    $table === $alias
                    || $table === $prefixedAlias
                    || str_ends_with($table, ' as '.$alias)
                    || str_ends_with($table, ' AS '.$alias)
                    || str_ends_with($table, ' as '.$prefixedAlias)
                    || str_ends_with($table, ' AS '.$prefixedAlias)
                );
            }
        ));

        if ($base->joins === []) {
            $base->joins = null;
        }
    }

    /**
     * @param  Builder<\App\Models\PdvVendaNfce>  $query
     */
    protected static function jaTemJoin(Builder $query): bool
    {
        $alias = self::JOIN_ALIAS;
        $prefix = (string) $query->getConnection()->getTablePrefix();
        $prefixedAlias = $prefix.$alias;
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if (! $join instanceof JoinClause) {
                continue;
            }

            $table = (string) $join->table;

            if ($table === $alias
                || $table === $prefixedAlias
                || str_ends_with($table, ' as '.$alias)
                || str_ends_with($table, ' AS '.$alias)
                || str_ends_with($table, ' as '.$prefixedAlias)
                || str_ends_with($table, ' AS '.$prefixedAlias)) {
                return true;
            }
        }

        return false;
    }
}
