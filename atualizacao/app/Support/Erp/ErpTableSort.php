<?php

namespace App\Support\Erp;

use Illuminate\Database\Eloquent\Builder;

final class ErpTableSort
{
    public static function orderByCodigoNumerico(Builder $query, string $direction = 'asc', string $column = 'codigo'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $query->orderByRaw('CAST('.$column.' AS UNSIGNED) '.$dir);

        return $query;
    }

    /**
     * Ordenação padrão por código numérico, sem sobrescrever sort escolhido pelo usuário.
     */
    public static function applyDefaultCodigoNumerico(Builder $query, string $direction, object $livewire, string $column = 'codigo'): Builder
    {
        if (method_exists($livewire, 'getTableSortColumn') && filled($livewire->getTableSortColumn())) {
            return $query;
        }

        return self::orderByCodigoNumerico($query, $direction, $column);
    }
}
