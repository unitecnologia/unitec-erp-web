<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\AjusteEstoqueResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Queries\AjusteEstoqueListQueryBuilder;
use App\Support\Erp\Reports\AjusteEstoqueListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AjusteEstoqueListagemReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = AjusteEstoqueListQueryBuilder::fromRequest($request);
        $ajustes = $builder->build()->get();
        $columns = AjusteEstoqueListagemReport::defaultColumns();

        $data = [
            'empresa' => $empresa,
            'ajustes' => $ajustes,
            'columns' => $columns,
            'columnLabels' => AjusteEstoqueListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE AJUSTES DE ESTOQUE',
            'periodLabel' => AjusteEstoqueListagemReport::periodSummary(
                $builder->informarPeriodo,
                $builder->periodoDe,
                $builder->periodoAte,
            ),
            'searchLabel' => filled($builder->localSearch)
                ? (AjusteEstoqueListagemReport::searchFieldLabels()[$builder->searchColumn] ?? 'Produto')
                    . ': ' . mb_strtoupper($builder->localSearch, 'UTF-8')
                : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($builder),
            'filterOptions' => [
                'campo' => AjusteEstoqueListagemReport::searchFieldLabels(),
            ],
            'reportUrl' => route('erp.reports.ajustes-estoque-listagem'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.ajustes-estoque-listagem-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->download('listagem-ajustes-estoque.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($ajustes, $columns);
        }

        return view('reports.ajustes-estoque-listagem', $data);
    }

    /**
     * @param  Collection<int, \App\Models\AjusteEstoque>  $ajustes
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $ajustes, array $columns): StreamedResponse
    {
        $labels = AjusteEstoqueListagemReport::columnDefinitions();

        return response()->streamDownload(function () use ($ajustes, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($ajustes as $ajuste) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => AjusteEstoqueListagemReport::cellValue($ajuste, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'listagem-ajustes-estoque.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterState(AjusteEstoqueListQueryBuilder $builder): array
    {
        return [
            'periodo' => $builder->informarPeriodo ? '1' : '0',
            'de' => $builder->periodoDe,
            'ate' => $builder->periodoAte,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
        ];
    }

    protected function closeUrl(AjusteEstoqueListQueryBuilder $builder): string
    {
        $params = array_filter([
            'campo' => $builder->searchColumn !== 'produto' ? $builder->searchColumn : null,
            'q' => $builder->localSearch,
        ], fn ($value): bool => filled($value));

        $url = AjusteEstoqueResource::getUrl('index');

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
