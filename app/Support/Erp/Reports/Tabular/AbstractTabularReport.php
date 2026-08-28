<?php

namespace App\Support\Erp\Reports\Tabular;

use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ReportEmpresaScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class AbstractTabularReport implements TabularReportDefinition
{
    /** Limite de dias no período padrão de preview (evita travar com ranges enormes). */
    protected const MAX_PERIOD_DAYS = 366;

    /**
     * Nome físico da tabela (com DB_PREFIX), para usar em DB::raw.
     */
    protected static function sqlTable(string $table): string
    {
        return DB::getTablePrefix() . $table;
    }

    public function filename(): string
    {
        return $this->slug();
    }

    /**
     * @return list<string>
     */
    public function numericColumns(): array
    {
        return [];
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public function resolveColumns(?array $requested): array
    {
        $allowed = array_keys($this->columns());

        if ($requested === null || $requested === []) {
            return $this->defaultColumns();
        }

        $columns = [];

        foreach ($requested as $column) {
            if (in_array($column, $allowed, true)) {
                $columns[] = $column;
            }
        }

        return $columns !== [] ? $columns : $this->defaultColumns();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    protected function buildTotals(array $rows, array $columns): array
    {
        $numeric = $this->numericColumns();
        $sums = array_fill_keys($numeric, 0.0);

        foreach ($rows as $row) {
            foreach ($numeric as $column) {
                if (! array_key_exists($column, $row)) {
                    continue;
                }

                $sums[$column] += $this->parseNumeric($row[$column] ?? null);
            }
        }

        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if (! in_array($column, $numeric, true)) {
                $totals[$column] = $labelPlaced ? '' : 'TOTAL';
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = $this->formatTotalColumn($column, $sums[$column]);
        }

        return $totals;
    }

    protected function formatTotalColumn(string $column, float $value): string
    {
        if (str_contains($column, 'qtd') || str_contains($column, 'estoque') || str_contains($column, 'quantidade')) {
            return static::formatQuantity($value);
        }

        if (str_contains($column, 'pct') || str_contains($column, 'percent') || str_contains($column, 'margem')) {
            return static::formatPercent($value);
        }

        return static::formatMoney($value);
    }

    protected function parseNumeric(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || $value === '') {
            return 0.0;
        }

        $normalized = str_replace(['.', ' ', '%'], ['', '', ''], $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function periodFromRequest(Request $request, ?CarbonInterface $defaultDe = null, ?CarbonInterface $defaultAte = null): array
    {
        $hoje = ErpTimezone::toLocal();
        $de = $this->parseDate($request->query('de'), $defaultDe ?? $hoje->copy()->startOfMonth());
        $ate = $this->parseDate($request->query('ate'), $defaultAte ?? $hoje->copy());

        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy(), $de->copy()];
        }

        // Preview automático no menu: evita ranges absurdos que travam PHP/browser.
        if ($de->diffInDays($ate) > static::MAX_PERIOD_DAYS) {
            $ate = $de->copy()->addDays(static::MAX_PERIOD_DAYS);
        }

        return [$de, $ate];
    }

    protected function parseDate(mixed $value, CarbonInterface $default): Carbon
    {
        if (! filled($value)) {
            return Carbon::instance($default)->startOfDay();
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return Carbon::instance($default)->startOfDay();
        }
    }

    protected function periodLabel(CarbonInterface $de, CarbonInterface $ate): string
    {
        return $de->format('d/m/Y') . ' a ' . $ate->format('d/m/Y');
    }

    /**
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>}>
     */
    protected function periodFilterFields(): array
    {
        return [
            ['key' => 'de', 'label' => 'Data de', 'type' => 'date'],
            ['key' => 'ate', 'label' => 'Data até', 'type' => 'date'],
        ];
    }

    /**
     * Filtro Empresa / Grupo no topo — null se a empresa da sessão não tem grupo.
     *
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>}>
     */
    protected function empresaFilterFields(): array
    {
        $field = ReportEmpresaScope::filterField();

        return $field ? [$field] : [];
    }

    /**
     * @param  list<array{key: string, label: string, type: string, options?: array<string, string>}>  $fields
     * @return list<array{key: string, label: string, type: string, options?: array<string, string>}>
     */
    protected function withEmpresaFilter(array $fields): array
    {
        return [...$this->empresaFilterFields(), ...$fields];
    }

    protected function empresaSummaryLine(Request $request): string
    {
        return ReportEmpresaScope::summaryLabel($request);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function withEmpresaFilterValue(array $filters, Request $request): array
    {
        if (ReportEmpresaScope::shouldShowFilter()) {
            $filters['empresa'] = ReportEmpresaScope::selectedValue($request);
        }

        return $filters;
    }

    /**
     * @param  list<string>  $summary
     * @return list<string>
     */
    protected function withEmpresaSummary(array $summary, Request $request): array
    {
        if (ReportEmpresaScope::shouldShowFilter()) {
            array_unshift($summary, $this->empresaSummaryLine($request));
        }

        return $summary;
    }

    /**
     * Inclui coluna EMPRESA quando o escopo é multi-empresa; remove quando não for.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    protected function columnsForEmpresaScope(array $columns, Request $request, string $after = 'data'): array
    {
        $multi = ReportEmpresaScope::shouldShowFilter()
            && ReportEmpresaScope::isMultiEmpresa($request);

        if ($multi && ! in_array('empresa', $columns, true)) {
            $insertAt = array_search($after, $columns, true);
            if ($insertAt === false) {
                array_unshift($columns, 'empresa');
            } else {
                array_splice($columns, $insertAt + 1, 0, ['empresa']);
            }
        }

        if (! $multi) {
            $columns = array_values(array_filter($columns, static fn (string $c): bool => $c !== 'empresa'));
        }

        return $columns;
    }

    protected function isMultiEmpresaScope(Request $request): bool
    {
        return ReportEmpresaScope::shouldShowFilter()
            && ReportEmpresaScope::isMultiEmpresa($request);
    }

    /**
     * @return list<string>
     */
    protected function withColumnsField(array $fields): array
    {
        $fields[] = [
            'key' => 'cols',
            'label' => 'Colunas',
            'type' => 'columns',
            'options' => $this->columns(),
        ];

        return $fields;
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function formatQuantity(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, 3, ',', '.');
    }

    public static function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.') . '%';
    }

    public static function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $summary
     * @return array{
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>,
     *     summary: list<string>,
     *     filters: array<string, mixed>,
     *     columns: list<string>
     * }
     */
    protected function result(array $filters, array $columns, array $rows, array $summary, bool $withTotals = true): array
    {
        $stringRows = [];

        foreach ($rows as $row) {
            $stringRow = [];

            foreach ($columns as $column) {
                $value = $row[$column] ?? '';
                $stringRow[$column] = is_string($value) ? $value : (string) $value;
            }

            $stringRows[] = $stringRow;
        }

        return [
            'rows' => $stringRows,
            'totals' => $withTotals ? $this->buildTotals($rows, $columns) : [],
            'summary' => $summary,
            'filters' => $filters,
            'columns' => $columns,
        ];
    }
}
