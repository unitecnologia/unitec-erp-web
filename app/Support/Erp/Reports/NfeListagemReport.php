<?php

namespace App\Support\Erp\Reports;

use App\Models\Nfe;
use Illuminate\Support\Carbon;

class NfeListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'numero' => 'NÚMERO',
            'serie' => 'SÉRIE',
            'data_emissao' => 'DT. EMISSÃO',
            'data_saida' => 'DT. SAÍDA',
            'cliente' => 'CLIENTE',
            'chave' => 'CHAVE',
            'protocolo' => 'PROTOCOLO',
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
            'data_saida',
            'cliente',
            'chave',
            'protocolo',
            'total',
            'status',
        ];
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

    public static function cellValue(Nfe $nfe, string $column): string
    {
        return match ($column) {
            'numero' => static::formatNumero($nfe->numero),
            'serie' => (string) ($nfe->serie ?? ''),
            'data_emissao' => static::formatDate($nfe->data_emissao),
            'data_saida' => static::formatDate($nfe->data_saida),
            'cliente' => (string) ($nfe->cliente?->nome_razao ?? ''),
            'chave' => static::formatChave($nfe->chave),
            'protocolo' => (string) ($nfe->protocolo ?? ''),
            'status' => Nfe::statusLabels()[$nfe->status] ?? (string) $nfe->status,
            'total' => static::formatMoney((float) $nfe->total),
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

        // Duas linhas no PDF (DomPDF corta com nowrap/overflow).
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
            'serie' => '4%',
            'data_emissao' => '7%',
            'data_saida' => '7%',
            'cliente' => '16%',
            'chave' => '32%',
            'protocolo' => '13%',
            'status' => '10%',
            'total' => '7%',
            default => 'auto',
        };
    }

    public static function columnCssClass(string $column): string
    {
        return match ($column) {
            'numero' => 'col-numero',
            'serie' => 'col-serie',
            'data_emissao', 'data_saida' => 'col-data',
            'cliente' => 'col-cliente',
            'chave' => 'col-chave',
            'protocolo' => 'col-protocolo',
            'status' => 'col-status',
            'total' => 'col-total',
            default => '',
        };
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    public static function columnRawValue(Nfe $nfe, string $column): ?float
    {
        return match ($column) {
            'total' => (float) $nfe->total,
            default => null,
        };
    }

    /**
     * @param  iterable<int, Nfe>  $nfes
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(iterable $nfes, array $columns): array
    {
        $nfesList = is_array($nfes) ? $nfes : iterator_to_array($nfes);
        $count = count($nfesList);
        $sums = array_fill_keys($columns, 0.0);

        foreach ($nfesList as $nfe) {
            foreach ($columns as $column) {
                $raw = static::columnRawValue($nfe, $column);

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

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return ['todas' => 'Todas'] + Nfe::statusLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function orderLabels(): array
    {
        return [
            'numero' => 'Número',
            'data_emissao' => 'Data emissão',
            'data_saida' => 'Data saída',
            'total' => 'Total',
            'cliente' => 'Cliente',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'numero' => 'Número',
            'data_emissao' => 'Dt. emissão',
            'data_saida' => 'Dt. saída',
            'cliente' => 'Cliente',
            'chave' => 'Chave',
            'protocolo' => 'Protocolo',
            'total' => 'Total',
        ];
    }

    public static function locateSummary(
        string $searchColumn,
        string $localSearch,
        ?string $localSearchDe = null,
        ?string $localSearchAte = null,
    ): ?string {
        if (in_array($searchColumn, ['data_emissao', 'data_saida'], true)) {
            $label = static::searchFieldLabels()[$searchColumn] ?? $searchColumn;
            $parts = array_filter([
                filled($localSearchDe) ? 'de ' . static::formatDate($localSearchDe) : null,
                filled($localSearchAte) ? 'até ' . static::formatDate($localSearchAte) : null,
            ]);

            return $parts !== [] ? $label . ': ' . implode(' ', $parts) : null;
        }

        if (filled($localSearch)) {
            $label = static::searchFieldLabels()[$searchColumn] ?? $searchColumn;

            return $label . ': ' . $localSearch;
        }

        return null;
    }

    public static function searchSummary(
        string $searchColumn,
        string $localSearch,
        string $chaveFilter,
    ): ?string {
        return static::locateSummary(
            filled($chaveFilter) ? 'chave' : $searchColumn,
            filled($chaveFilter) ? $chaveFilter : $localSearch,
        );
    }

    public static function periodSummary(?string $de, ?string $ate): ?string
    {
        $parts = array_filter([
            filled($de) ? 'de ' . static::formatDate($de) : null,
            filled($ate) ? 'até ' . static::formatDate($ate) : null,
        ]);

        return $parts !== [] ? 'PERÍODO: ' . implode(' ', $parts) : null;
    }
}
