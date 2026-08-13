<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\ContaReceberResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Queries\ContaReceberListQueryBuilder;
use App\Support\Erp\Reports\ContaReceberCartoesReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContaReceberCartoesReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $empresa = $this->currentEmpresa();
        $builder = ContaReceberListQueryBuilder::fromRequest($request);

        // Relatório de cartões: sempre força forma=cartao.
        $builder->formaFilter = 'cartao';

        $contas = $builder->build()->get();
        $columns = ContaReceberCartoesReport::resolveColumns($request->query('cols'));

        $data = [
            'empresa' => $empresa,
            'contas' => $contas,
            'columns' => $columns,
            'columnLabels' => ContaReceberCartoesReport::columnDefinitions(),
            'reportTitle' => 'RELATÓRIO DE CARTÕES',
            'situacaoLabel' => ContaReceberCartoesReport::situacaoLabels()[$builder->situacaoFilter] ?? 'Todos',
            'periodoLabel' => $this->periodoLabel($builder),
            'searchLabel' => filled($builder->localSearch)
                ? mb_strtoupper($builder->searchColumn . ': ' . $builder->localSearch, 'UTF-8')
                : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'totaisBandeira' => ContaReceberCartoesReport::totaisPorBandeira($contas),
            'filters' => $this->filterState($request, $builder, $columns),
            'filterOptions' => [
                'situacao' => ContaReceberCartoesReport::situacaoLabels(),
                'columns' => ContaReceberCartoesReport::columnDefinitions(),
            ],
            'reportUrl' => route('erp.reports.contas-receber-cartoes'),
            'closeUrl' => $this->closeUrl($builder),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.contas-receber-cartoes-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('relatorio-cartoes.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($contas, $columns);
        }

        return view('reports.contas-receber-cartoes', $data);
    }

    /**
     * @param  list<string>  $columns
     */
    protected function csvResponse(Collection $contas, array $columns): StreamedResponse
    {
        $labels = ContaReceberCartoesReport::columnDefinitions();

        return response()->streamDownload(function () use ($contas, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $column): string => $labels[$column] ?? $column, $columns), ';');

            foreach ($contas as $conta) {
                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => ContaReceberCartoesReport::cellValue($conta, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'relatorio-cartoes.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    protected function filterState(
        Request $request,
        ContaReceberListQueryBuilder $builder,
        array $columns,
    ): array {
        return [
            'situacao' => $builder->situacaoFilter,
            'forma' => 'cartao',
            'cliente' => $builder->clienteFilter,
            'campo' => $builder->searchColumn,
            'q' => $builder->localSearch,
            'de' => $builder->periodoDe,
            'ate' => $builder->periodoAte,
            'cols' => $request->has('cols')
                ? $columns
                : ContaReceberCartoesReport::defaultColumns(),
        ];
    }

    protected function periodoLabel(ContaReceberListQueryBuilder $builder): string
    {
        if (! filled($builder->periodoDe) && ! filled($builder->periodoAte)) {
            return 'Todos';
        }

        $de = filled($builder->periodoDe)
            ? ContaReceberCartoesReport::formatDate($builder->periodoDe)
            : '…';
        $ate = filled($builder->periodoAte)
            ? ContaReceberCartoesReport::formatDate($builder->periodoAte)
            : '…';

        return $de . ' a ' . $ate;
    }

    protected function closeUrl(ContaReceberListQueryBuilder $builder): string
    {
        $params = array_filter(
            [
                ...$builder->reportFilters(),
                'forma' => 'cartao',
            ],
            fn ($value): bool => filled($value),
        );

        $url = ContaReceberResource::getUrl('index');

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
