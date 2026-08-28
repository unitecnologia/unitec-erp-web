<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\ChartableTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\BuildsReportChartScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricoVendasReport extends AbstractTabularReport implements ChartableTabularReport
{
    use BuildsReportChartScope;

    /** @var list<string> */
    private const CHART_COLORS = [
        '#1e5a9e',
        '#15803d',
        '#b45309',
        '#7c3aed',
        '#be123c',
        '#0f766e',
        '#0369a1',
        '#a16207',
    ];

    public function slug(): string
    {
        return 'historico-vendas';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE VENDAS';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data' => 'DATA',
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'vendedor' => 'VENDEDOR',
            'forma' => 'FORMA PAGTO',
            'tipo' => 'TIPO',
            'plataforma' => 'ORIGEM',
            'status' => 'STATUS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'vendedor',
            'forma',
            'tipo',
            'plataforma',
            'status',
            'total',
        ];
    }

    public function numericColumns(): array
    {
        return ['total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => ['todos' => 'Todos'] + Venda::statusLabels(),
            ],
        ]));
    }

    public function chartConfig(): array
    {
        $empresaUi = $this->chartEmpresaUiConfig();

        return [
            'type' => 'line',
            'show_empresa_select' => $empresaUi['show_empresa_select'],
            'empresas' => $empresaUi['empresas'],
            'default_empresa' => $empresaUi['default_empresa'],
            'supports_yoy' => false,
            'supports_granularity' => true,
            'default_granularity' => 'month',
        ];
    }

    public function chartData(Request $request): array
    {
        $granularity = $this->chartGranularity($request);

        if ($granularity === 'day') {
            return $this->chartDataDaily($request);
        }

        return $this->chartDataMonthly($request);
    }

    private function chartDataMonthly(Request $request): array
    {
        [$de, $ate] = $this->chartPeriodClamp($request);
        $axis = $this->chartMonthAxis($de, $ate);
        $status = (string) $request->query('status', Venda::STATUS_FECHADO);
        $empresaIds = $this->chartEmpresaIds($request);
        $hasEmpresaCol = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $splitByEmpresa = $hasEmpresaCol && count($empresaIds) > 1;

        $labels = ReportEmpresaScope::labelsById();
        $datasets = $this->buildChartDatasets(
            $axis['keys'],
            $status,
            $empresaIds,
            $splitByEmpresa,
            $hasEmpresaCol,
            $labels,
            $de,
            $ate,
            granularity: 'month',
        );

        return [
            'labels' => $axis['labels'],
            'datasets' => $datasets,
            'meta' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'period_label' => $this->periodLabel($de, $ate),
                'empresas' => $empresaIds,
                'granularity' => 'month',
                'clamped_to_12_months' => true,
                'cards' => $this->chartSummaryCards($axis['labels'], $datasets, 'month'),
            ],
        ];
    }

    private function chartDataDaily(Request $request): array
    {
        [$de, $ate] = $this->chartPeriodClampForChart($request);
        $axis = $this->chartDayAxis($de, $ate);
        $status = (string) $request->query('status', Venda::STATUS_FECHADO);
        $empresaIds = $this->chartEmpresaIds($request);
        $hasEmpresaCol = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $splitByEmpresa = $hasEmpresaCol && count($empresaIds) > 1;

        $labels = ReportEmpresaScope::labelsById();
        $datasets = $this->buildChartDatasets(
            $axis['keys'],
            $status,
            $empresaIds,
            $splitByEmpresa,
            $hasEmpresaCol,
            $labels,
            $de,
            $ate,
            granularity: 'day',
        );

        return [
            'labels' => $axis['labels'],
            'datasets' => $datasets,
            'meta' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'period_label' => $this->periodLabel($de, $ate),
                'empresas' => $empresaIds,
                'granularity' => 'day',
                'clamped_to_31_days' => true,
                'cards' => $this->chartSummaryCards($axis['labels'], $datasets, 'day'),
            ],
        ];
    }

    /**
     * @param  list<string>  $axisKeys
     * @param  list<int>  $empresaIds
     * @param  array<int, string>  $labels
     * @return list<array<string, mixed>>
     */
    private function buildChartDatasets(
        array $axisKeys,
        string $status,
        array $empresaIds,
        bool $splitByEmpresa,
        bool $hasEmpresaCol,
        array $labels,
        $de,
        $ate,
        string $granularity,
    ): array {
        $datasets = [];
        $isDay = $granularity === 'day';

        if ($splitByEmpresa) {
            $byEmpresa = $isDay
                ? $this->aggregateDailyTotals($de, $ate, $status, $empresaIds, groupByEmpresa: true)
                : $this->aggregateMonthlyTotals($de, $ate, $status, $empresaIds, groupByEmpresa: true);

            foreach ($empresaIds as $i => $empresaId) {
                $color = self::CHART_COLORS[$i % count(self::CHART_COLORS)];
                $name = $labels[$empresaId] ?? ('Empresa #'.$empresaId);
                $map = $byEmpresa[$empresaId] ?? [];
                $data = $isDay
                    ? $this->chartFillDays($axisKeys, $map)
                    : $this->chartFillMonths($axisKeys, $map);
                $datasets[] = $this->lineDataset($name, $data, $color);
            }

            return $datasets;
        }

        $byEmpresa = $hasEmpresaCol
            ? ($isDay
                ? $this->aggregateDailyTotals($de, $ate, $status, $empresaIds, groupByEmpresa: false)
                : $this->aggregateMonthlyTotals($de, $ate, $status, $empresaIds, groupByEmpresa: false))
            : ($isDay
                ? $this->aggregateDailyTotals($de, $ate, $status, [], groupByEmpresa: false)
                : $this->aggregateMonthlyTotals($de, $ate, $status, [], groupByEmpresa: false));

        $map = $byEmpresa[0] ?? [];
        $label = count($empresaIds) === 1
            ? ($labels[$empresaIds[0]] ?? 'Vendas')
            : 'Vendas';
        $data = $isDay
            ? $this->chartFillDays($axisKeys, $map)
            : $this->chartFillMonths($axisKeys, $map);

        $datasets[] = $this->lineDataset($label, $data, self::CHART_COLORS[0]);

        return $datasets;
    }

    private function chartGranularity(Request $request): string
    {
        $raw = mb_strtolower(trim((string) $request->query('chart_granularity', 'month')), 'UTF-8');

        return $raw === 'day' ? 'day' : 'month';
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'data',
        );
        $status = (string) $request->query('status', Venda::STATUS_FECHADO);
        $multiEmpresa = $this->isMultiEmpresaScope($request);

        $query = Venda::query()
            ->with(['cliente', 'vendedor', 'empresa'])
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('data')
            ->orderByDesc('id');

        if (Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            ReportEmpresaScope::applyToQuery($query, $request, 'empresa_id');
        }

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $rows = $query->limit(5000)->get()->map(function (Venda $venda) use ($multiEmpresa): array {
            $row = [
                'numero' => (string) $venda->numero,
                'data' => static::formatDate($venda->data),
                'cliente' => (string) ($venda->cliente?->nome_razao ?? ''),
                'vendedor' => $venda->vendedorNome(),
                'forma' => (string) ($venda->forma_pagamento ?? ''),
                'tipo' => Venda::tipoLabels()[$venda->tipo] ?? (string) $venda->tipo,
                'plataforma' => $venda->plataformaLabel(),
                'status' => Venda::statusLabels()[$venda->status] ?? (string) $venda->status,
                'total' => static::formatMoney((float) $venda->total),
            ];

            if ($multiEmpresa) {
                $row['empresa'] = ReportEmpresaScope::labelEmpresa($venda->empresa);
            }

            return $row;
        })->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'status' => $status,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO: '.$this->periodLabel($de, $ate),
                'STATUS: '.mb_strtoupper($status === 'todos' ? 'TODOS' : (Venda::statusLabels()[$status] ?? $status), 'UTF-8'),
            ], $request),
        );
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, array<string, float>>  empresaId => [Y-m => total]; se !groupByEmpresa usa chave 0
     */
    private function aggregateMonthlyTotals(
        $de,
        $ate,
        string $status,
        array $empresaIds,
        bool $groupByEmpresa,
    ): array {
        $query = Venda::query()
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');

        if ($hasEmpresa && $empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds)->whereNotNull('empresa_id');
        }

        $yearExpr = 'YEAR('.static::sqlTable('vendas').'.data)';
        $monthExpr = 'MONTH('.static::sqlTable('vendas').'.data)';

        if ($groupByEmpresa && $hasEmpresa) {
            $rows = $query
                ->groupBy(DB::raw($yearExpr), DB::raw($monthExpr), 'empresa_id')
                ->orderBy(DB::raw($yearExpr))
                ->orderBy(DB::raw($monthExpr))
                ->get([
                    'empresa_id',
                    DB::raw($yearExpr.' as ano'),
                    DB::raw($monthExpr.' as mes'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);

            $out = [];
            foreach ($rows as $row) {
                $eid = (int) $row->empresa_id;
                $ym = sprintf('%04d-%02d', (int) $row->ano, (int) $row->mes);
                $out[$eid][$ym] = (float) $row->total;
            }

            return $out;
        }

        $rows = $query
            ->groupBy(DB::raw($yearExpr), DB::raw($monthExpr))
            ->orderBy(DB::raw($yearExpr))
            ->orderBy(DB::raw($monthExpr))
            ->get([
                DB::raw($yearExpr.' as ano'),
                DB::raw($monthExpr.' as mes'),
                DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
            ]);

        $out = [0 => []];
        foreach ($rows as $row) {
            $ym = sprintf('%04d-%02d', (int) $row->ano, (int) $row->mes);
            $out[0][$ym] = (float) $row->total;
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, array<string, float>>  empresaId => [Y-m-d => total]; se !groupByEmpresa usa chave 0
     */
    private function aggregateDailyTotals(
        $de,
        $ate,
        string $status,
        array $empresaIds,
        bool $groupByEmpresa,
    ): array {
        $query = Venda::query()
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');

        if ($hasEmpresa && $empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds)->whereNotNull('empresa_id');
        }

        $dataCol = static::sqlTable('vendas').'.data';

        if ($groupByEmpresa && $hasEmpresa) {
            $rows = $query
                ->groupBy(DB::raw($dataCol), 'empresa_id')
                ->orderBy(DB::raw($dataCol))
                ->get([
                    'empresa_id',
                    DB::raw($dataCol.' as dia'),
                    DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
                ]);

            $out = [];
            foreach ($rows as $row) {
                $eid = (int) $row->empresa_id;
                $ymd = Carbon::parse((string) $row->dia)->format('Y-m-d');
                $out[$eid][$ymd] = (float) $row->total;
            }

            return $out;
        }

        $rows = $query
            ->groupBy(DB::raw($dataCol))
            ->orderBy(DB::raw($dataCol))
            ->get([
                DB::raw($dataCol.' as dia'),
                DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
            ]);

        $out = [0 => []];
        foreach ($rows as $row) {
            $ymd = Carbon::parse((string) $row->dia)->format('Y-m-d');
            $out[0][$ymd] = (float) $row->total;
        }

        return $out;
    }

    /**
     * Cards KPI a partir das séries já agregadas (sem query extra).
     *
     * @param  list<string>  $periodLabels
     * @param  list<array<string, mixed>>  $datasets
     * @return array<string, array<string, mixed>>
     */
    private function chartSummaryCards(array $periodLabels, array $datasets, string $granularity): array
    {
        $periodCount = count($periodLabels);
        $perPeriod = array_fill(0, max($periodCount, 0), 0.0);

        foreach ($datasets as $dataset) {
            $data = $dataset['data'] ?? [];
            foreach ($data as $i => $value) {
                if (! array_key_exists((int) $i, $perPeriod)) {
                    continue;
                }
                $perPeriod[(int) $i] += (float) $value;
            }
        }

        $total = array_sum($perPeriod);
        $bestIdx = 0;
        $bestVal = $perPeriod[0] ?? 0.0;
        for ($i = 1; $i < $periodCount; $i++) {
            if ($perPeriod[$i] > $bestVal) {
                $bestVal = $perPeriod[$i];
                $bestIdx = $i;
            }
        }
        $avg = $periodCount > 0 ? ($total / $periodCount) : 0.0;
        $bestLabel = (string) ($periodLabels[$bestIdx] ?? '');

        if ($granularity === 'day') {
            return [
                'total' => [
                    'label' => 'Total vendido',
                    'value' => $total,
                    'text' => static::formatMoney($total),
                ],
                'best_day' => [
                    'label' => 'Melhor dia',
                    'value' => $bestVal,
                    'text' => static::formatMoney($bestVal),
                    'day' => $bestLabel,
                ],
                'avg' => [
                    'label' => 'Média diária',
                    'value' => $avg,
                    'text' => static::formatMoney($avg),
                ],
            ];
        }

        return [
            'total' => [
                'label' => 'Total vendido',
                'value' => $total,
                'text' => static::formatMoney($total),
            ],
            'best_month' => [
                'label' => 'Melhor mês',
                'value' => $bestVal,
                'text' => static::formatMoney($bestVal),
                'month' => $bestLabel,
            ],
            'avg' => [
                'label' => 'Média mensal',
                'value' => $avg,
                'text' => static::formatMoney($avg),
            ],
        ];
    }

    /**
     * @param  list<float>  $data
     * @return array<string, mixed>
     */
    private function lineDataset(string $label, array $data, string $color): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'fill' => false,
            'borderWidth' => 2,
            'pointRadius' => 2.5,
            'pointHoverRadius' => 4,
            'tension' => 0.3,
        ];
    }
}
