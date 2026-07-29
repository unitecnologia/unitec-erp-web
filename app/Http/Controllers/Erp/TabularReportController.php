<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use App\Support\Erp\Reports\Tabular\TabularReportDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TabularReportController extends Controller
{
    public function __invoke(Request $request, string $slug): View|Response|StreamedResponse
    {
        abort_unless(ReportRegistry::has($slug), 404);

        $report = ReportRegistry::make($slug);
        abort_unless(ErpAccess::currentCan($report->permission()), 403);

        $built = $report->build($request);
        $empresa = $this->currentEmpresa();

        $data = [
            'empresa' => $empresa,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportTitle' => $report->title(),
            'columnLabels' => $report->columns(),
            'columns' => $built['columns'],
            'rows' => $built['rows'],
            'totals' => $built['totals'],
            'summary' => $built['summary'],
            'numericColumns' => $report->numericColumns(),
            'filters' => $built['filters'],
            'filterFields' => $report->filterFields(),
            'printedAt' => now(),
            'reportUrl' => ReportRegistry::route($slug),
            'closeUrl' => url('/admin'),
            'autoPrint' => $request->boolean('auto'),
            'emptyMessage' => 'Nenhum registro encontrado para os filtros informados.',
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.tabular-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download($report->filename() . '.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($report, $built['rows'], $built['columns']);
        }

        return view('reports.tabular', $data);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $columns
     */
    protected function csvResponse(TabularReportDefinition $report, array $rows, array $columns): StreamedResponse
    {
        $labels = $report->columns();

        return response()->streamDownload(function () use ($rows, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $columns),
                    ';',
                );
            }

            fclose($handle);
        }, $report->filename() . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
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

        if (! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
