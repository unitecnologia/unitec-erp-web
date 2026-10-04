<?php

namespace App\Support\Erp;

use Illuminate\Database\Eloquent\Builder;

/**
 * Seleção de 1 ou 2 campos de pesquisa (OR). Usado por Pessoas e Produtos.
 */
final class ErpSearchFieldSelection
{
    /**
     * @param  list<mixed>  $active
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function normalize(array $active, array $allowed, string $fallback): array
    {
        $active = array_values(array_unique(array_filter(
            $active,
            fn (mixed $column): bool => is_string($column) && $column !== '' && in_array($column, $allowed, true),
        )));

        if ($active === []) {
            $fallback = in_array($fallback, $allowed, true) ? $fallback : (string) ($allowed[0] ?? '');

            return $fallback !== '' ? [$fallback] : [];
        }

        return array_values(array_slice($active, 0, 2));
    }

    /**
     * @param  list<string>  $active
     * @param  list<string>  $allowed
     * @return list<string>|null null quando a seleção não muda (mínimo 1 ou terceiro campo)
     */
    public static function toggle(array $active, string $column, array $allowed, string $fallback): ?array
    {
        if (! in_array($column, $allowed, true)) {
            return null;
        }

        $active = self::normalize($active, $allowed, $fallback);

        if (in_array($column, $active, true)) {
            if (count($active) <= 1) {
                return null;
            }

            return array_values(array_filter(
                $active,
                fn (string $item): bool => $item !== $column,
            ));
        }

        if (count($active) >= 2) {
            return null;
        }

        $active[] = $column;

        return $active;
    }

    /**
     * @param  list<string>  $columns
     */
    public static function applyOr(Builder $query, array $columns, callable $apply): void
    {
        $columns = array_values($columns);

        if ($columns === []) {
            return;
        }

        if (count($columns) === 1) {
            $apply($query, $columns[0]);

            return;
        }

        $query->where(function (Builder $outer) use ($columns, $apply): void {
            foreach ($columns as $index => $column) {
                $callback = function (Builder $inner) use ($apply, $column): void {
                    $apply($inner, $column);
                };

                if ($index === 0) {
                    $outer->where($callback);
                } else {
                    $outer->orWhere($callback);
                }
            }
        });
    }
}
