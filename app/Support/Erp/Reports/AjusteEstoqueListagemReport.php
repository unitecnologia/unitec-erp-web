<?php

namespace App\Support\Erp\Reports;

use App\Models\AjusteEstoque;
use Illuminate\Support\Collection;

class AjusteEstoqueListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'data' => 'DATA',
            'codigo' => 'CÓDIGO',
            'produto' => 'PRODUTO',
            'referencia' => 'REFERÊNCIA',
            'qtd_ajust' => 'QTD. AJUST.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['data', 'codigo', 'produto', 'qtd_ajust'];
    }

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'produto' => 'Produto',
            'codigo' => 'Código',
            'data' => 'Data',
        ];
    }

    public static function cellValue(AjusteEstoque $ajuste, string $column): string
    {
        $product = $ajuste->product;

        return match ($column) {
            'data' => $ajuste->data?->format('d/m/Y') ?? '',
            'codigo' => (string) ($product?->codigo ?? ''),
            'produto' => (string) ($product?->descricao ?? ''),
            'referencia' => (string) ($product?->referencia ?? ''),
            'qtd_ajust' => number_format((float) $ajuste->qtd_ajust, 3, ',', '.'),
            default => '',
        };
    }

    public static function isNumericColumn(string $column): bool
    {
        return $column === 'qtd_ajust';
    }

    /**
     * @param  Collection<int, AjusteEstoque>  $ajustes
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(Collection $ajustes, array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            if ($column === 'qtd_ajust') {
                $sum = $ajustes->sum(fn (AjusteEstoque $row): float => (float) $row->qtd_ajust);
                $totals[$column] = number_format($sum, 3, ',', '.');
            } elseif ($column === 'produto') {
                $totals[$column] = 'TOTAL';
            } else {
                $totals[$column] = '';
            }
        }

        return $totals;
    }

    public static function periodSummary(bool $informarPeriodo, string $de, string $ate): string
    {
        if (! $informarPeriodo) {
            return 'TODOS';
        }

        $deLabel = filled($de) ? date('d/m/Y', strtotime($de)) : '—';
        $ateLabel = filled($ate) ? date('d/m/Y', strtotime($ate)) : '—';

        return $deLabel . ' ATÉ ' . $ateLabel;
    }
}
