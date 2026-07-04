<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\Orcamento;
use App\Support\Erp\Orcamento\OrcamentoBobinaBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrcamentoReportController
{
    public function __invoke(Request $request, Orcamento $orcamento): View|Response
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $orcamento->load([
            'cliente',
            'vendedor',
            'itens' => fn ($query) => $query->orderBy('item')->with('product'),
        ]);

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        $numero = $this->formatNumero($orcamento->numero);
        $statusLabel = mb_strtoupper(Orcamento::statusLabels()[$orcamento->status] ?? $orcamento->status, 'UTF-8');
        $empresaEndereco = $this->formatEmpresaEndereco($empresa);
        $bobina = $request->boolean('bobina');

        $data = [
            'orcamento' => $orcamento,
            'empresa' => $empresa,
            'numero' => $numero,
            'statusLabel' => $statusLabel,
            'empresaEndereco' => $empresaEndereco,
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'autoPrint' => $request->boolean('auto'),
            'embedded' => $request->boolean('embed'),
            'printedAt' => now(),
            'bobina' => $bobina,
        ];

        if ($bobina) {
            $data['bobinaLines'] = app(OrcamentoBobinaBuilder::class)->buildLines(
                $orcamento,
                $empresa,
                $numero,
                $statusLabel,
                $empresaEndereco,
            );
        }

        if ($request->boolean('pdf')) {
            if ($bobina) {
                $height = max(600, (count($data['bobinaLines']) + 4) * 14);

                return Pdf::loadView('reports.orcamento-bobina-pdf', $data)
                    ->setPaper([0, 0, 226.77, $height], 'portrait')
                    ->download('orcamento-bobina-' . $numero . '.pdf');
            }

            return Pdf::loadView('reports.orcamento-pdf', $data)
                ->setPaper('a4', 'portrait')
                ->download('orcamento-' . $numero . '.pdf');
        }

        return view($bobina ? 'reports.orcamento-bobina' : 'reports.orcamento', $data);
    }

    protected function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', $numero);

        return $digits > 0 ? (string) $digits : $numero;
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
