<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\VendaResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Queries\VendaListQueryBuilder;
use App\Support\Erp\Reports\VendaListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendaListagemReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = VendaListQueryBuilder::fromRequest($request);
        $vendas = $builder->build()->get();
        $columns = VendaListagemReport::resolveColumns($request->query('cols'));

        $data = [
            'empresa' => $empresa,
            'vendas' => $vendas,
            'columns' => $columns,
            'columnLabels' => VendaListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE VENDAS',
            'statusLabel' => VendaListagemReport::statusLabels()[$builder->statusFilter] ?? 'Todos',
            'tipoLabel' => VendaListagemReport::tipoLabels()[$builder->tipoFilter] ?? 'Todos',
            'orderLabel' => VendaListagemReport::orderLabels()[$builder->orderBy] ?? 'Número',
            'searchLabel' => VendaListagemReport::searchSummary(
                $builder->searchColumn,
                $builder->localSearch,
                $builder->localSearchDe,
                $builder->localSearchAte,
                $builder->localSearchHoraDe,
                $builder->localSearchHoraAte,
            ),
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($request, $builder, $columns),
            'filterOptions' => [
                'status' => VendaListagemReport::statusLabels(),
                'tipo' => VendaListagemReport::tipoLabels(),
                'ordenar' => VendaListagemReport::orderLabels(),
                'campo' => VendaListagemReport::searchFieldLabels(),
                'columns' => VendaListagemReport::columnDefinitions(),
            ],
            'reportUrl' => route('erp.reports.vendas-listagem'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.vendas-listagem-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('listagem-vendas.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($vendas, $columns);
        }

        return view('reports.vendas-listagem', $data);
    }

    /**
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $vendas, array $columns): StreamedResponse
    {
        $labels = VendaListagemReport::columnDefinitions();

        return response()->streamDownload(function () use ($vendas, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($vendas as $venda) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => VendaListagemReport::cellValue($venda, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'listagem-vendas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function filterState(
        Request $request,
        VendaListQueryBuilder $builder,
        array $columns,
    ): array {
        return [
            'status' => $builder->statusFilter,
            'tipo' => $builder->tipoFilter,
            'ordenar' => $builder->orderBy,
            'dir' => $builder->orderDirection,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
            'de' => $builder->localSearchDe,
            'ate' => $builder->localSearchAte,
            'hora_de' => $builder->localSearchHoraDe,
            'hora_ate' => $builder->localSearchHoraAte,
            'cols' => $request->has('cols')
                ? $columns
                : VendaListagemReport::defaultColumns(),
        ];
    }

    protected function closeUrl(VendaListQueryBuilder $builder): string
    {
        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = VendaResource::getUrl('index');

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
