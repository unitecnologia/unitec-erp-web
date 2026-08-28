<?php

namespace App\Http\Controllers\Erp;

use App\Filament\Resources\ReciboResource;
use App\Models\Empresa;
use App\Models\Recibo;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Recibo\ReciboBobinaBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReciboReportController
{
    public function __invoke(Request $request, Recibo $recibo): View|Response
    {
        abort_unless(ErpAccess::currentCan('recibos.print'), 403);

        $empresa = $this->currentEmpresa();
        $bobina = $request->boolean('bobina');

        $data = [
            'recibo' => $recibo,
            'empresa' => $empresa,
            'empresaEndereco' => $this->formatEmpresaEndereco($empresa),
            'logoDataUri' => $this->logoDataUri($empresa),
            'logoUrl' => $empresa?->logoUrl(),
            'extenso' => $recibo->ensureExtenso(),
            'printedAt' => now(),
            'closeUrl' => ReciboResource::getUrl('index'),
            'autoPrint' => $request->boolean('auto'),
            'embedded' => $request->boolean('embed'),
            'bobina' => $bobina,
        ];

        if ($bobina) {
            $data['bobinaLines'] = app(ReciboBobinaBuilder::class)->buildLines($recibo, $empresa);
        }

        if ($request->boolean('pdf')) {
            if ($bobina) {
                $height = max(600, (count($data['bobinaLines']) + 4) * 14);

                return Pdf::loadView('reports.recibo-bobina-pdf', $data)
                    ->setPaper([0, 0, 226.77, $height], 'portrait')
                    ->download('recibo-bobina-'.$recibo->codigo.'.pdf');
            }

            $pdf = Pdf::loadView('reports.recibo-pdf', $data)
                ->setPaper('a4', 'portrait');

            $filename = 'recibo-'.$recibo->codigo.'.pdf';

            return $request->boolean('inline')
                ? $pdf->stream($filename)
                : $pdf->download($filename);
        }

        return view($bobina ? 'reports.recibo-bobina' : 'reports.recibo', $data);
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
            filled($empresa->cidade) ? mb_strtoupper(trim($empresa->cidade), 'UTF-8') : null,
            filled($empresa->uf) ? mb_strtoupper(trim($empresa->uf), 'UTF-8') : null,
        ]);

        return $partes === [] ? '' : implode(', ', $partes);
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
