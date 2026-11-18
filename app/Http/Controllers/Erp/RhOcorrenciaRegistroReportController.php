<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\RhOcorrenciaResource;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RhOcorrencia;
use App\Support\Rh\RhOcorrenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RhOcorrenciaRegistroReportController extends Controller
{
    public function __invoke(Request $request, RhOcorrencia $ocorrencia): View
    {
        $ocorrencia->load([
            'funcionario:id,codigo,nome,cpf',
            'user:id,name',
        ]);

        $empresa = $this->currentEmpresa();
        $podeVerRestrito = RhOcorrenciaService::podeVerConteudoRestrito();

        return view('reports.rh-ocorrencia-registro', [
            'empresa' => $empresa,
            'ocorrencia' => $ocorrencia,
            'podeVerRestrito' => $podeVerRestrito,
            'descricaoExibicao' => $ocorrencia->acesso_restrito && ! $podeVerRestrito
                ? 'Conteúdo restrito — requer permissão de acesso ao módulo.'
                : (string) $ocorrencia->descricao,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'empresaDoc' => $this->formatEmpresaDoc($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'printedAt' => now(),
            'closeUrl' => RhOcorrenciaResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
        ]);
    }

    protected function currentEmpresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    protected function formatEmpresaDoc(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $cnpj = filled($empresa->cnpj) ? 'CNPJ: '.$empresa->cnpj : null;
        $ie = filled($empresa->ie) ? 'IE: '.$empresa->ie : null;

        return implode(' | ', array_filter([$cnpj, $ie]));
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
            filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : null,
            filled($empresa->uf) ? mb_strtoupper(trim($empresa->uf), 'UTF-8') : null,
        ]);

        return $partes !== [] ? implode(' - ', $partes) : '';
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
