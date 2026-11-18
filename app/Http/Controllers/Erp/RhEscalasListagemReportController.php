<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\RhEscalaResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Support\Erp\Reports\RhEscalasListagemReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RhEscalasListagemReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $empresa = $this->currentEmpresa();
        $vinculos = RhEscalasListagemReport::buildQuery($request)->get();
        $columns = RhEscalasListagemReport::defaultColumns();
        $status = (string) $request->query('status', 'ativos');

        return view('reports.rh-escalas-listagem', [
            'empresa' => $empresa,
            'vinculos' => $vinculos,
            'columns' => $columns,
            'columnLabels' => RhEscalasListagemReport::columnDefinitions(),
            'reportTitle' => 'LISTAGEM DE VÍNCULOS DE ESCALA — RH',
            'statusLabel' => RhEscalasListagemReport::statusLabels()[$status] ?? 'Ativos',
            'searchLabel' => filled($request->query('q'))
                ? mb_strtoupper(trim((string) $request->query('q')), 'UTF-8')
                : null,
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'reportUrl' => route('erp.reports.rh-escalas-listagem'),
            'closeUrl' => RhEscalaResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
            'maxRows' => RhEscalasListagemReport::MAX_ROWS,
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
