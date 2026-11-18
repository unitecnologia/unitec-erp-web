<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\RhExameResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Reports\RhExamesListagemReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RhExamesListagemReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $empresa = $this->currentEmpresa();
        $exames = RhExamesListagemReport::buildQuery($request)->get();
        $columns = RhExamesListagemReport::defaultColumns();

        $status = (string) $request->query('status', 'realizados');
        $alerta = (int) $request->query('alerta', 30);
        if (! in_array($alerta, [7, 15, 30], true)) {
            $alerta = 30;
        }

        return view('reports.rh-exames-listagem', [
            'empresa' => $empresa,
            'exames' => $exames,
            'columns' => $columns,
            'columnLabels' => RhExamesListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE EXAMES / ASO — RH',
            'statusLabel' => RhExamesListagemReport::statusLabels()[$status] ?? 'Realizados',
            'alertaLabel' => $alerta.' dias',
            'alertaDias' => $alerta,
            'searchLabel' => filled($request->query('q'))
                ? mb_strtoupper(trim((string) $request->query('q')), 'UTF-8')
                : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.rh-exames-listagem'),
            'closeUrl' => RhExameResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
            'maxRows' => RhExamesListagemReport::MAX_ROWS,
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
            $endereco .= ', '.implode(' - ', $partes);
        }

        return 'END: '.$endereco;
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

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
