<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Filament\Resources\ProductResource;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Support\Erp\Queries\ProductListQueryBuilder;
use App\Support\Erp\Reports\ProductListagemReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductEstoqueReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = ProductListQueryBuilder::fromRequest($request, $empresa);
        $products = $builder->build()->get();
        $isCritico = $builder->estoqueFilter === 'critico';
        $columns = $request->has('cols')
            ? ProductListagemReport::resolveColumns($request->query('cols'))
            : ($isCritico
                ? ProductListagemReport::defaultColumnsCritico()
                : ProductListagemReport::resolveColumns(null));

        $data = [
            'empresa' => $empresa,
            'products' => $products,
            'columns' => $columns,
            'columnLabels' => ProductListagemReport::columnDefinitions(),
            'reportTitle' => $isCritico ? 'ESTOQUE CRÍTICO' : 'LISTAGEM DE PRODUTOS',
            'statusLabel' => ProductListagemReport::statusLabels()[$builder->statusFilter] ?? 'Ativos',
            'orderLabel' => ProductListagemReport::orderLabels()[$builder->orderBy] ?? 'Descrição',
            'estoqueFilterLabel' => ProductListagemReport::estoqueFilterLabels()[$builder->estoqueFilter] ?? 'Todos',
            'searchLabel' => filled($builder->localSearch)
                ? (ProductListagemReport::searchFieldLabels()[$builder->searchColumn] ?? 'Descrição') . ': ' . $builder->localSearch
                : null,
            'grupoFilter' => filled($builder->grupoFilter) ? $builder->grupoFilter : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'filters' => $this->filterState($request, $builder, $columns),
            'filterOptions' => [
                'status' => ProductListagemReport::statusLabels(),
                'ordenar' => ProductListagemReport::orderLabels(),
                'estoque' => ProductListagemReport::estoqueFilterLabels(),
                'campo' => ProductListagemReport::searchFieldLabels(),
                'columns' => ProductListagemReport::columnDefinitions(),
            ],
            'grupoOptions' => $this->grupoFilterOptions($builder->grupoFilter),
            'reportUrl' => route('erp.reports.produtos-estoque'),
            'closeUrl' => ProductResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.produtos-listagem-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('listagem-produtos.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($products, $columns);
        }

        return view('reports.produtos-listagem', $data);
    }

    /**
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $products, array $columns): StreamedResponse
    {
        $labels = ProductListagemReport::columnDefinitions();

        return response()->streamDownload(function () use ($products, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($products as $product) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => ProductListagemReport::cellValue($product, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'listagem-produtos.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function grupoFilterOptions(string $selected = ''): array
    {
        $options = Grupo::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome')
            ->all();

        $selected = trim($selected);

        if ($selected !== '' && ! in_array($selected, $options, true)) {
            $options[] = $selected;
            sort($options, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $options;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function filterState(
        Request $request,
        ProductListQueryBuilder $builder,
        array $columns,
    ): array {
        return [
            'status' => $builder->statusFilter,
            'ordenar' => $builder->orderBy,
            'estoque' => $builder->estoqueFilter,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
            'grupo' => $builder->grupoFilter,
            'cols' => $request->has('cols')
                ? $columns
                : ($builder->estoqueFilter === 'critico'
                    ? ProductListagemReport::defaultColumnsCritico()
                    : ProductListagemReport::defaultColumns()),
        ];
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
