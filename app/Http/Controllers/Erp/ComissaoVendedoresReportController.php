<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ComissaoVendedoresReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComissaoVendedoresReportController extends Controller
{
    public function __invoke(Request $request): View|Response|StreamedResponse
    {
        $hoje = ErpTimezone::toLocal();
        $de = $this->parseDate($request->query('de'), $hoje->copy()->startOfMonth());
        $ate = $this->parseDate($request->query('ate'), $hoje->copy());

        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy(), $de->copy()];
        }

        $vendedorFiltro = $request->query('vendedor', 'todos');

        $query = Venda::query()
            ->with('vendedor')
            ->where('status', Venda::STATUS_FECHADO)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()]);

        if ($vendedorFiltro !== 'todos' && is_numeric($vendedorFiltro)) {
            $query->where('vendedor_id', (int) $vendedorFiltro);
        }

        $vendas = $query->orderBy('data')->get();
        $relatorio = ComissaoVendedoresReport::build($vendas);

        $empresa = $this->currentEmpresa();

        $data = [
            'empresa' => $empresa,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'reportTitle' => 'COMISSÃO DE VENDEDORES',
            'linhas' => $relatorio['linhas'],
            'totais' => $relatorio['totais'],
            'periodoLabel' => $de->format('d/m/Y') . ' a ' . $ate->format('d/m/Y'),
            'printedAt' => now(),
            'filters' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'vendedor' => $vendedorFiltro,
            ],
            'filterOptions' => [
                'vendedor' => ['todos' => '<todos>'] + Vendedor::query()->orderBy('nome')->pluck('nome', 'id')->all(),
            ],
            'reportUrl' => route('erp.reports.comissao-vendedores'),
            'closeUrl' => url('/admin'),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.comissao-vendedores-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->download('comissao-vendedores.pdf');
        }

        if ($request->boolean('csv')) {
            return $this->csvResponse($relatorio['linhas']);
        }

        return view('reports.comissao-vendedores', $data);
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     */
    protected function csvResponse(array $linhas): StreamedResponse
    {
        return response()->streamDownload(function () use ($linhas): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['VENDEDOR', 'QTD', 'À VISTA', '% AV', 'COM. AV', 'A PRAZO', '% AP', 'COM. AP', 'TOTAL', 'COMISSÃO'], ';');

            foreach ($linhas as $l) {
                fputcsv($handle, [
                    $l['nome'],
                    $l['qtd'],
                    ComissaoVendedoresReport::formatMoney((float) $l['total_avista']),
                    ComissaoVendedoresReport::formatMoney((float) $l['comissao_av']),
                    ComissaoVendedoresReport::formatMoney((float) $l['comissao_avista']),
                    ComissaoVendedoresReport::formatMoney((float) $l['total_aprazo']),
                    ComissaoVendedoresReport::formatMoney((float) $l['comissao_ap']),
                    ComissaoVendedoresReport::formatMoney((float) $l['comissao_aprazo']),
                    ComissaoVendedoresReport::formatMoney((float) $l['total_geral']),
                    ComissaoVendedoresReport::formatMoney((float) $l['comissao_total']),
                ], ';');
            }

            fclose($handle);
        }, 'comissao-vendedores.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function parseDate(mixed $value, Carbon $default): Carbon
    {
        if (! filled($value)) {
            return $default;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return $default;
        }
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
