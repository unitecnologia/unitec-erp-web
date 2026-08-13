<?php

namespace App\Support\Erp\Reports;

use App\Models\Compra;
use Illuminate\Support\Carbon;

class CompraListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data_emissao' => 'DT. EMISSÃO',
            'data_entrada' => 'DT. ENTRADA',
            'numero_nota' => 'Nº NOTA',
            'fornecedor' => 'FORNECEDOR',
            'chave_nfe' => 'CHAVE',
            'status' => 'SITUAÇÃO',
            'total' => 'TOTAL',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return [
            'numero',
            'data_emissao',
            'data_entrada',
            'numero_nota',
            'fornecedor',
            'chave_nfe',
            'total',
            'status',
        ];
    }

    public static function cellValue(Compra $compra, string $column): string
    {
        return match ($column) {
            'numero' => static::formatNumero($compra->numero),
            'data_emissao' => static::formatDate($compra->data_emissao),
            'data_entrada' => static::formatDate($compra->data_entrada),
            'numero_nota' => (string) ($compra->numero_nota ?? ''),
            'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? ''),
            'chave_nfe' => static::formatChave($compra->chave_nfe),
            'status' => Compra::statusLabels()[$compra->status] ?? (string) $compra->status,
            'total' => static::formatMoney((float) $compra->total),
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

    public static function formatChave(mixed $chave): string
    {
        $digits = preg_replace('/\D/', '', (string) $chave) ?? '';

        if (strlen($digits) !== 44) {
            return trim((string) $chave);
        }

        $groups = str_split($digits, 4);

        return implode(' ', array_slice($groups, 0, 6))
            ."\n"
            .implode(' ', array_slice($groups, 6));
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

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function isNumericColumn(string $column): bool
    {
        return $column === 'total';
    }

    public static function columnWidthPercent(string $column): string
    {
        return match ($column) {
            'numero' => '5%',
            'data_emissao', 'data_entrada' => '7%',
            'numero_nota' => '7%',
            'fornecedor' => '16%',
            'chave_nfe' => '32%',
            'status' => '10%',
            'total' => '7%',
            default => 'auto',
        };
    }

    public static function columnCssClass(string $column): string
    {
        return match ($column) {
            'numero' => 'col-numero',
            'data_emissao', 'data_entrada' => 'col-data',
            'numero_nota' => 'col-serie',
            'fornecedor' => 'col-cliente',
            'chave_nfe' => 'col-chave',
            'status' => 'col-status',
            'total' => 'col-total',
            default => '',
        };
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    public static function columnRawValue(Compra $compra, string $column): ?float
    {
        return match ($column) {
            'total' => (float) $compra->total,
            default => null,
        };
    }

    /**
     * @param  iterable<int, Compra>  $compras
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(iterable $compras, array $columns): array
    {
        $comprasList = is_array($compras) ? $compras : iterator_to_array($compras);
        $count = count($comprasList);
        $sums = array_fill_keys($columns, 0.0);

        foreach ($comprasList as $compra) {
            foreach ($columns as $column) {
                $raw = static::columnRawValue($compra, $column);

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
}
