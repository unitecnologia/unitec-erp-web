<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\NfeResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Queries\NfeListQueryBuilder;
use App\Support\Erp\Reports\NfeListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NfeListagemReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = NfeListQueryBuilder::fromRequest($request);

        // Tenant: o relatório sempre usa a empresa da sessão, ignorando empresa_id vindo da URL.
        if ($empresa) {
            $builder = new NfeListQueryBuilder(
                statusFilter: $builder->statusFilter,
                searchColumn: $builder->searchColumn,
                localSearch: $builder->localSearch,
                localSearchDe: $builder->localSearchDe,
                localSearchAte: $builder->localSearchAte,
                orderBy: $builder->orderBy,
                orderDirection: $builder->orderDirection,
                empresaId: (int) $empresa->id,
            );
        }

        $nfes = $builder->build()->get();
        $columns = NfeListagemReport::resolveColumns($request->query('cols'));

        $data = [
            'empresa' => $empresa,
            'nfes' => $nfes,
            'columns' => $columns,
            'columnLabels' => NfeListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE NF-e',
            'statusLabel' => NfeListagemReport::statusLabels()[$builder->statusFilter] ?? 'Aberta',
            'orderLabel' => NfeListagemReport::orderLabels()[$builder->orderBy] ?? 'Número',
            'locateLabel' => NfeListagemReport::locateSummary(
                $builder->searchColumn,
                $builder->localSearch,
                $builder->localSearchDe,
                $builder->localSearchAte,
            ),
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($request, $builder, $columns),
            'filterOptions' => [
                'status' => NfeListagemReport::statusLabels(),
                'ordenar' => NfeListagemReport::orderLabels(),
                'campo' => NfeListagemReport::searchFieldLabels(),
                'columns' => NfeListagemReport::columnDefinitions(),
            ],
            'reportUrl' => route('erp.reports.nfe-listagem'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.nfe-listagem-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('listagem-nfe.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($nfes, $columns);
        }

        return view('reports.nfe-listagem', $data);
    }

    /**
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $nfes, array $columns): StreamedResponse
    {
        $labels = NfeListagemReport::columnDefinitions();

        return response()->streamDownload(function () use ($nfes, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($nfes as $nfe) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => str_replace(
                            ["\r", "\n"],
                            ['', ' '],
                            NfeListagemReport::cellValue($nfe, $column),
                        ),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'listagem-nfe.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function filterState(
        Request $request,
        NfeListQueryBuilder $builder,
        array $columns,
    ): array {
        return [
            'status' => $builder->statusFilter,
            'de' => $builder->localSearchDe,
            'ate' => $builder->localSearchAte,
            'ordenar' => $builder->orderBy,
            'dir' => $builder->orderDirection,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
            'cols' => $request->has('cols')
                ? $columns
                : NfeListagemReport::defaultColumns(),
        ];
    }

    protected function closeUrl(NfeListQueryBuilder $builder): string
    {
        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = NfeResource::getUrl('index');

        return $params === [] ? $url : $url . '?' . http_build_query($params);
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
