<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\PersonResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Queries\PersonListQueryBuilder;
use App\Support\Erp\Reports\PersonListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonListagemReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = PersonListQueryBuilder::fromRequest($request);
        $people = $builder->build()->get();
        $columns = PersonListagemReport::resolveColumns($request->query('cols'));

        $data = [
            'empresa' => $empresa,
            'people' => $people,
            'columns' => $columns,
            'columnLabels' => PersonListagemReport::columnDefinitions(),
            'reportTitle' => PersonListagemReport::reportTitle($builder->tipoFilter),
            'statusLabel' => PersonListagemReport::statusLabels()[$builder->statusFilter] ?? 'Ativos',
            'tipoLabel' => PersonListagemReport::tipoLabels()[$builder->tipoFilter] ?? 'Clientes',
            'orderLabel' => PersonListagemReport::orderLabels()[$builder->orderBy] ?? 'Código',
            'searchLabel' => filled($builder->localSearch)
                ? (PersonListagemReport::searchFieldLabels()[$builder->searchColumn] ?? 'Razão/Nome') . ': ' . $builder->localSearch
                : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($request, $builder, $columns),
            'filterOptions' => [
                'tipo' => PersonListagemReport::tipoLabels(),
                'status' => PersonListagemReport::statusLabels(),
                'ordenar' => PersonListagemReport::orderLabels(),
                'campo' => PersonListagemReport::searchFieldLabels(),
                'columns' => PersonListagemReport::columnDefinitions(),
            ],
            'reportUrl' => route('erp.reports.pessoas-listagem'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.pessoas-listagem-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('listagem-pessoas.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($people, $columns);
        }

        return view('reports.pessoas-listagem', $data);
    }

    /**
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $people, array $columns): StreamedResponse
    {
        $labels = PersonListagemReport::columnDefinitions();

        return response()->streamDownload(function () use ($people, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($people as $person) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => PersonListagemReport::cellValue($person, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'listagem-pessoas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function filterState(
        Request $request,
        PersonListQueryBuilder $builder,
        array $columns,
    ): array {
        return [
            'tipo' => $builder->tipoFilter,
            'status' => $builder->statusFilter,
            'ordenar' => $builder->orderBy,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
            'cols' => $request->has('cols')
                ? $columns
                : PersonListagemReport::defaultColumns(),
        ];
    }

    protected function closeUrl(PersonListQueryBuilder $builder): string
    {
        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $url = PersonResource::getUrl('index');

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
