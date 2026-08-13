<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Pages\ExpedicaoBipagemPage;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Entrega;
use App\Support\Erp\Reports\ExpedicaoRetiradaReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ExpedicaoRetiradaReportController extends Controller
{
    public function __invoke(Request $request, Entrega $entrega): View|Response
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $entrega->load(['itens', 'venda', 'cliente']);

        $empresa = $this->currentEmpresa();
        $linhas = ExpedicaoRetiradaReport::buildLinhas($entrega);

        $data = [
            'empresa' => $empresa,
            'entrega' => $entrega,
            'linhas' => $linhas,
            'numeroPedido' => ExpedicaoRetiradaReport::formatNumeroPedido($entrega),
            'totalQuantidade' => ExpedicaoRetiradaReport::totalQuantidade($entrega),
            'reportTitle' => 'ROMANEIO DE RETIRADA',
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.expedicao-retirada', ['entrega' => $entrega->id]),
            'closeUrl' => ExpedicaoBipagemPage::getUrl(['ids' => (string) $entrega->id]),
            'autoPrint' => $request->boolean('auto'),
        ];

        if ($request->boolean('pdf')) {
            return Pdf::loadView('reports.expedicao-retirada-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->download('romaneio-retirada.pdf');
        }

        return view('reports.expedicao-retirada', $data);
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
