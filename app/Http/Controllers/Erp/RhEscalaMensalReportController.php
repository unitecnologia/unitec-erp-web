<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\RhEscalaResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RhFuncionario;
use App\Support\Rh\RhEscalaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RhEscalaMensalReportController extends Controller
{
    public function __invoke(Request $request, RhEscalaService $escalaService): View
    {
        $funcionarioId = (int) $request->query('funcionario', 0);
        $ano = (int) $request->query('ano', (int) now()->year);
        $mes = (int) $request->query('mes', (int) now()->month);

        if ($mes < 1 || $mes > 12) {
            $mes = (int) now()->month;
        }

        $funcionario = $funcionarioId > 0 ? RhFuncionario::query()->find($funcionarioId) : null;
        $dias = $funcionario
            ? $escalaService->calendarioMensal($funcionarioId, $ano, $mes)
            : [];

        $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

        $empresa = $this->currentEmpresa();

        return view('reports.rh-escala-mensal', [
            'empresa' => $empresa,
            'funcionario' => $funcionario,
            'dias' => $dias,
            'ano' => $ano,
            'mes' => $mes,
            'mesLabel' => $meses[$mes] ?? (string) $mes,
            'reportTitle' => 'ESCALA MENSAL — RH',
            'printedAt' => now(),
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'closeUrl' => RhEscalaResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
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
