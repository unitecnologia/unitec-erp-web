<?php

namespace App\Support\Erp\Reports;

use App\Models\Venda;
use Illuminate\Support\Carbon;

class VendaListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data' => 'DATA',
            'cliente' => 'CLIENTE',
            'vendedor' => 'VENDEDOR',
            'plataforma' => 'PLATAFORMA',
            'meio_pagamento' => 'MEIO DE PAGAMENTO',
            'total' => 'TOTAL',
            'status' => 'SITUAÇÃO',
            'tipo' => 'TIPO',
            'hora' => 'HORA',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(static::columnDefinitions());
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public static function resolveColumns(?array $requested): array
    {
        $allowed = array_keys(static::columnDefinitions());

        if ($requested === null || $requested === []) {
            return static::defaultColumns();
        }

        $columns = [];

        foreach ($requested as $column) {
            if (in_array($column, $allowed, true)) {
                $columns[] = $column;
            }
        }

        return $columns !== [] ? $columns : static::defaultColumns();
    }

    public static function cellValue(Venda $venda, string $column): string
    {
        return match ($column) {
            'numero' => static::formatNumero($venda->numero),
            'data' => static::formatDate($venda->data),
            'cliente' => (string) ($venda->cliente?->nome_razao ?? ''),
            'vendedor' => $venda->vendedorNome(),
            'meio_pagamento' => mb_strtoupper((string) ($venda->forma_pagamento ?? ''), 'UTF-8'),
            'total' => static::formatMoney((float) $venda->total),
            'status' => Venda::statusLabels()[$venda->status] ?? (string) $venda->status,
            'tipo' => Venda::tipoLabels()[$venda->tipo] ?? (string) $venda->tipo,
            'hora' => static::formatHora($venda->hora),
            'plataforma' => $venda->plataformaLabel(),
            default => '',
        };
    }

    public static function formatNumero(mixed $numero): string
    {
        if ($numero === null || $numero === '') {
            return '';
        }

        $trimmed = ltrim((string) $numero, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    public static function formatDate(mixed $value): string
    {
        if (! filled($value)) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse((string) $value)->format('d/m/Y');
    }

    public static function formatHora(mixed $value): string
    {
        if (! filled($value)) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }

    public static function isNumericColumn(string $column): bool
    {
        return $column === 'total';
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    public static function columnRawValue(Venda $venda, string $column): ?float
    {
        return match ($column) {
            'total' => (float) $venda->total,
            default => null,
        };
    }

    /**
     * @param  iterable<int, Venda>  $vendas
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(iterable $vendas, array $columns): array
    {
        $vendasList = is_array($vendas) ? $vendas : iterator_to_array($vendas);
        $count = count($vendasList);
        $sums = array_fill_keys($columns, 0.0);

        foreach ($vendasList as $venda) {
            foreach ($columns as $column) {
                $raw = static::columnRawValue($venda, $column);

                if ($raw !== null) {
                    $sums[$column] += $raw;
                }
            }
        }

        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'numero') {
                $totals[$column] = (string) $count;

                continue;
            }

            if (static::isSummableColumn($column)) {
                $totals[$column] = static::formatMoney($sums[$column]);

                continue;
            }

            $totals[$column] = $labelPlaced ? '' : 'TOTAL';
            $labelPlaced = true;
        }

        return $totals;
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return ['todos' => 'Todos'] + Venda::statusLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return ['todos' => 'Todos'] + Venda::tipoLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function orderLabels(): array
    {
        return [
            'numero' => 'Número',
            'data' => 'Data',
            'total' => 'Total',
            'hora' => 'Hora',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'numero' => 'Número',
            'data' => 'Data',
            'cliente' => 'Cliente',
            'vendedor' => 'Vendedor',
            'meio_pagamento' => 'Meio de Pagamento',
            'total' => 'Total',
            'situacao' => 'Situação',
            'tipo' => 'Tipo',
            'hora' => 'Hora',
            'plataforma' => 'Plataforma',
        ];
    }

    public static function searchSummary(
        string $searchColumn,
        string $localSearch,
        string $localSearchDe,
        string $localSearchAte,
        string $localSearchHoraDe,
        string $localSearchHoraAte,
    ): ?string {
        if ($searchColumn === 'data') {
            $parts = array_filter([
                filled($localSearchDe) ? 'de ' . static::formatDate($localSearchDe) : null,
                filled($localSearchAte) ? 'até ' . static::formatDate($localSearchAte) : null,
            ]);

            return $parts !== [] ? 'DATA: ' . implode(' ', $parts) : null;
        }

        if ($searchColumn === 'hora') {
            $parts = array_filter([
                filled($localSearchHoraDe) ? 'de ' . $localSearchHoraDe : null,
                filled($localSearchHoraAte) ? 'até ' . $localSearchHoraAte : null,
            ]);

            return $parts !== [] ? 'HORA: ' . implode(' ', $parts) : null;
        }

        if (filled($localSearch)) {
            $label = static::searchFieldLabels()[$searchColumn] ?? $searchColumn;
            $value = $localSearch;

            if ($searchColumn === 'plataforma') {
                $value = Venda::plataformaLabels()[$localSearch] ?? $localSearch;
            }

            return $label . ': ' . $value;
        }

        return null;
    }
}
