<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Reports\Tabular\ChartableTabularReport;
use App\Support\Erp\Reports\Tabular\ExpandableTabularReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use App\Support\Erp\Reports\Tabular\TabularReportDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularReportController extends Controller
{
    public function __invoke(Request $request, string $slug): View|Response|StreamedResponse|RedirectResponse
    {
        abort_unless(ReportRegistry::has($slug), 404);

        $report = ReportRegistry::make($slug);
        abort_unless(ErpAccess::currentCan($report->permission()), 403);

        $requiresGenerate = $report->requiresExplicitGenerate();
        $wantsExport = $request->boolean('pdf') || $request->boolean('csv');
        // Telas compactas de filtros (sem pré-visualização vazia).
        $usesVendasGeralFilters = $requiresGenerate && $slug === 'historico-vendas';
        $usesLucratividadePorVendaFilters = $requiresGenerate && $slug === 'lucratividade-por-venda';
        $usesExternalFilters = $usesVendasGeralFilters || $usesLucratividadePorVendaFilters;

        if ($usesExternalFilters && ! $request->boolean('gerar') && ! $wantsExport) {
            if ($usesLucratividadePorVendaFilters) {
                return redirect()->to($this->lucratividadePorVendaFiltersUrl($request));
            }

            return redirect()->to($this->vendasGeralFiltersUrl($request));
        }

        // Exportações do fluxo "gerar explícito" exigem ?gerar=1 (evita PDF/CSV da abertura vazia).
        if ($requiresGenerate && $wantsExport && ! $request->boolean('gerar')) {
            abort(422, 'Gere o relatório antes de exportar.');
        }

        $built = $report->build($request);
        $meta = is_array($built['meta'] ?? null) ? $built['meta'] : [];
        $empresa = $this->currentEmpresa();
        $supportsChart = $report instanceof ChartableTabularReport
            && (! $requiresGenerate || $request->boolean('gerar'))
            && (bool) ($meta['show_chart'] ?? true);

        $columnWidths = [];
        foreach ($built['columns'] as $column) {
            $percent = $report->columnWidthPercent($column, $built['columns']);
            if ($percent !== null) {
                $columnWidths[$column] = $percent;
            }
        }

        $truncated = (bool) ($meta['truncated'] ?? false);
        $rowCount = count($built['rows'] ?? []);
        $emptyMessage = (string) ($meta['empty_message'] ?? 'Nenhum registro encontrado para os filtros informados.');
        $pendingGenerate = $requiresGenerate && ! $usesExternalFilters && ! $request->boolean('gerar') && ! $wantsExport;

        if ($requiresGenerate && $request->boolean('gerar') && $rowCount === 0 && ($meta['total_count'] ?? 0) === 0) {
            $emptyMessage = 'Nenhum registro encontrado para os filtros informados.';
        }

        $filtersBackUrl = null;
        if ($usesVendasGeralFilters) {
            $filtersBackUrl = $this->vendasGeralFiltersUrl($request);
        } elseif ($usesLucratividadePorVendaFilters) {
            $filtersBackUrl = $this->lucratividadePorVendaFiltersUrl($request);
        }

        $data = [
            'empresa' => $empresa,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $this->logoPublicUrl($empresa),
            'reportTitle' => $report->title(),
            'columnLabels' => array_merge($report->columns(), is_array($built['column_labels'] ?? null) ? $built['column_labels'] : []),
            'columns' => $built['columns'],
            'columnWidths' => $columnWidths,
            'nowrapColumns' => $report->nowrapColumns(),
            'rows' => $built['rows'],
            'totals' => $built['totals'],
            'summary' => $built['summary'],
            'numericColumns' => $built['numeric_columns'] ?? $report->numericColumns(),
            'filters' => $built['filters'],
            'filterFields' => $usesExternalFilters ? [] : $report->filterFields(),
            'printedAt' => now(),
            'reportUrl' => ReportRegistry::route($slug),
            'closeUrl' => $this->resolveCloseUrl($request),
            'autoPrint' => $request->boolean('auto'),
            'emptyMessage' => $emptyMessage,
            'supportsChart' => $supportsChart,
            'chartConfig' => $supportsChart ? $report->chartConfig() : null,
            'chartDataUrl' => $supportsChart ? route('erp.reports.tabular.chart', ['slug' => $slug]) : null,
            'supportsRowExpand' => $report instanceof ExpandableTabularReport && (bool) ($meta['row_expand'] ?? true),
            'detailsUrl' => $report instanceof ExpandableTabularReport
                ? route('erp.reports.tabular.details', ['slug' => $slug])
                : null,
            'requiresExplicitGenerate' => $requiresGenerate,
            'pendingGenerate' => $pendingGenerate,
            'hideFilterSidebar' => $usesExternalFilters,
            'filtersBackUrl' => $filtersBackUrl,
            'reportMeta' => $meta,
            'extraSections' => is_array($built['extra_sections'] ?? null) ? $built['extra_sections'] : [],
            'paperOrientation' => $report->paperOrientation() === 'portrait' ? 'portrait' : 'landscape',
        ];

        if ($request->boolean('pdf')) {
            $filename = $report->filename().($truncated ? '-parcial' : '').'.pdf';
            $orientation = ($data['paperOrientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';

            return Pdf::loadView('reports.tabular-pdf', $data)
                ->setPaper('a4', $orientation)
                ->download($filename);
        }

        if ($request->boolean('csv')) {
            $filename = $report->filename().($truncated ? '-parcial' : '');

            return $this->csvResponse($report, $built['rows'], $built['columns'], $filename, $meta);
        }

        return view('reports.tabular', $data);
    }

    public function chart(Request $request, string $slug): JsonResponse
    {
        abort_unless(ReportRegistry::has($slug), 404);

        $report = ReportRegistry::make($slug);
        abort_unless(ErpAccess::currentCan($report->permission()), 403);
        abort_unless($report instanceof ChartableTabularReport, 404);

        return response()->json($report->chartData($request));
    }

    public function details(Request $request, string $slug): JsonResponse
    {
        abort_unless(ReportRegistry::has($slug), 404);

        $report = ReportRegistry::make($slug);
        abort_unless(ErpAccess::currentCan($report->permission()), 403);
        abort_unless($report instanceof ExpandableTabularReport, 404);

        return response()->json($report->expandDetails($request));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $columns
     * @param  array<string, mixed>  $meta
     */
    protected function csvResponse(
        TabularReportDefinition $report,
        array $rows,
        array $columns,
        ?string $filename = null,
        array $meta = [],
    ): StreamedResponse {
        $labels = $report->columns();

        return response()->streamDownload(function () use ($rows, $columns, $labels, $meta): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            if (! empty($meta['truncated'])) {
                fputcsv($handle, [
                    'ATENCAO',
                    'Exportacao PARCIAL — listagem truncada. Totais do universo filtrado: '
                    .(string) ($meta['total_count'] ?? '')
                    .' venda(s) / R$ '
                    .(string) ($meta['total_sum_formatted'] ?? ''),
                ], ';');
            }

            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $columns),
                    ';',
                );
            }

            fclose($handle);
        }, ($filename ?: $report->filename()).'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    protected function resolveCloseUrl(Request $request): string
    {
        $returnUrl = trim((string) $request->query('return'));

        if ($returnUrl !== '' && str_starts_with($returnUrl, url('/admin'))) {
            return $returnUrl;
        }

        return url('/admin');
    }

    /**
     * URL da tela de filtros do Vendas Geral, preservando parâmetros (sem gerar/pdf/csv).
     */
    protected function vendasGeralFiltersUrl(Request $request): string
    {
        return $this->filtersPageUrl($request, route('erp.reports.vendas-geral.filtros'));
    }

    /**
     * URL da tela compacta de filtros da Lucratividade por Venda.
     * Não reenvia de/ate — a tela de filtros sempre abre com mês atual + hoje (fuso local).
     */
    protected function lucratividadePorVendaFiltersUrl(Request $request): string
    {
        return $this->filtersPageUrl(
            $request,
            route('erp.reports.lucratividade-por-venda.filtros'),
            ['de', 'ate'],
        );
    }

    /**
     * @param  array<string, mixed>  $extraUnset
     */
    protected function filtersPageUrl(Request $request, string $base, array $extraUnset = []): string
    {
        $params = $request->query();
        foreach (array_merge(['gerar', 'pdf', 'csv', 'auto', 'return'], $extraUnset) as $key) {
            unset($params[$key]);
        }

        if ($params === []) {
            return $base;
        }

        return $base.'?'.http_build_query($params);
    }

    /**
     * URL da logomarca via rota autenticada (não depende do symlink public/storage).
     */
    protected function logoPublicUrl(?Empresa $empresa): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', (string) $empresa->logo_path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $version = $empresa->updated_at?->timestamp ?? time();

        return route('erp.storage.file', ['path' => $path]).'?v='.$version;
    }

    protected function formatEmpresaEndereco(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $partes = array_filter([
            filled($empresa->endereco) ? mb_strtoupper(trim($empresa->endereco), 'UTF-8') : null,
            filled($empresa->numero) ? trim((string) $empresa->numero) : null,
            filled($empresa->bairro) ? mb_strtoupper(trim($empresa->bairro), 'UTF-8') : null,
        ]);

        if ($partes === []) {
            return '';
        }

        $endereco = array_shift($partes);

        if ($partes !== []) {
            $endereco .= ', ' . implode(' - ', $partes);
        }

        return 'END: ' . $endereco;
    }

    protected function logoDataUri(?Empresa $empresa): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', (string) $empresa->logo_path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);
        if ($contents === false || $contents === null || $contents === '') {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';
        // Alguns drivers devolvem false; evita data URI inválido (ícone quebrado).
        if (! is_string($mime) || $mime === '') {
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
