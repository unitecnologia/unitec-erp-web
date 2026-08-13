<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Pages\ExpedicaoBipagemPage;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Entrega;
use App\Support\Erp\Reports\ExpedicaoSeparacaoReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpedicaoSeparacaoReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $entregaIds = $this->parseIds($request->query('ids', ''));

        abort_if($entregaIds === [], 404);

        $entregas = Entrega::query()
            ->with(['itens.product', 'venda'])
            ->whereIn('id', $entregaIds)
            ->orderBy('numero')
            ->get();

        abort_if($entregas->isEmpty(), 404);

        $empresa = $this->currentEmpresa();
        $ordenacao = $this->normalizeOrdenacao($request->query('ord', 'localizacao'));
        $columns = ExpedicaoSeparacaoReport::defaultColumns();
        $linhas = ExpedicaoSeparacaoReport::buildLinhas($entregas, $ordenacao);

        $data = [
            'empresa' => $empresa,
            'entregas' => $entregas,
            'linhas' => $linhas,
            'columns' => $columns,
            'columnLabels' => ExpedicaoSeparacaoReport::columnDefinitions(),
            'reportTitle' => 'RELATÓRIO DE SEPARAÇÃO',
            'pedidosSummary' => ExpedicaoSeparacaoReport::pedidosSummary($entregas),
            'ordenacao' => $ordenacao,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.expedicao-separacao', [
                'ids' => implode(',', $entregaIds),
                'ord' => $ordenacao,
            ]),
            'closeUrl' => ExpedicaoBipagemPage::getUrl(['ids' => implode(',', $entregaIds)]),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.expedicao-separacao-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->download('separacao-expedicao.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($linhas, $columns);
        }

        return view('reports.expedicao-separacao', $data);
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<string>  $columns
     */
    protected function csvResponse(array $linhas, array $columns): StreamedResponse
    {
        $labels = ExpedicaoSeparacaoReport::columnDefinitions();

        return response()->streamDownload(function () use ($linhas, $columns, $labels): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv(
                $handle,
                array_map(fn (string $column): string => $labels[$column] ?? $column, $columns),
                ';',
            );

            foreach ($linhas as $linha) {
                if (ExpedicaoSeparacaoReport::isCorredorSeparatorRow($linha)) {
                    fputcsv($handle, [$linha['label'] ?? '', '', '', '', '', ''], ';');

                    continue;
                }

                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string => ExpedicaoSeparacaoReport::cellValue($linha, $column),
                        $columns,
                    ),
                    ';',
                );
            }

            fclose($handle);
        }, 'separacao-expedicao.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<int>
     */
    protected function parseIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
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

    protected function normalizeOrdenacao(?string $ordenacao): string
    {
        if ($ordenacao === 'padrao') {
            return 'localizacao';
        }

        return in_array($ordenacao, ['localizacao', 'pedido', 'alfabetica', 'codigo', 'quantidade'], true)
            ? $ordenacao
            : 'localizacao';
    }
}
