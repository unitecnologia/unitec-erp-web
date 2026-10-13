<?php

namespace App\Support\Erp;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class ErpTableSort
{
    public static function orderByCodigoNumerico(
        Builder $query,
        string $direction = 'asc',
        string $column = 'codigo',
        bool $desempateIdDesc = false,
    ): Builder {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Coluna de ordenação inválida.');
        }

        // getTable() vem sem prefixo; em orderByRaw o grammar NÃO aplica o prefixo.
        $table = $query->getConnection()->getTablePrefix().$query->getModel()->getTable();
        $query->orderByRaw('CAST(`'.$table.'`.`'.$column.'` AS UNSIGNED) '.$dir);

        if ($desempateIdDesc) {
            $query->orderByDesc($query->getModel()->getKeyName());
        }

        return $query;
    }

    /**
     * Ordenação padrão por código numérico, sem sobrescrever sort escolhido pelo usuário.
     */
    public static function applyDefaultCodigoNumerico(
        Builder $query,
        string $direction,
        object $livewire,
        string $column = 'codigo',
        bool $desempateIdDesc = false,
    ): Builder {
        if (method_exists($livewire, 'getTableSortColumn') && filled($livewire->getTableSortColumn())) {
            return $query;
        }

        return self::orderByCodigoNumerico($query, $direction, $column, $desempateIdDesc);
    }
}
