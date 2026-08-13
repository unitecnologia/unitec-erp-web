<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\Nfe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NfeEtiquetaVolumeReportController
{
    public function __invoke(Request $request, Nfe $nfe): View
    {
        abort_unless(Auth::check(), 403);

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId && (int) $nfe->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $nfe->loadMissing(['cliente', 'transportadora', 'empresa']);
        $empresa = $nfe->empresa instanceof Empresa
            ? $nfe->empresa
            : Empresa::query()->find($nfe->empresa_id);

        $qvol = max(1, (int) ($nfe->qvol ?? 1));
        $pad = max(2, strlen((string) $qvol));

        // Sempre 1..qvol na mesma folha A4 (economiza papel).
        $volumes = array_map(
            static fn (int $atual): string => sprintf('%0'.$pad.'d/%0'.$pad.'d', $atual, $qvol),
            range(1, $qvol),
        );

        $cliente = $nfe->cliente;
        $destino = trim(implode(' - ', array_filter([
            mb_strtoupper(trim((string) ($cliente?->cidade_nome ?? '')), 'UTF-8'),
            strtoupper(trim((string) ($cliente?->uf ?? ''))),
        ])));

        return view('reports.nfe-etiqueta-volume', [
            'nfe' => $nfe,
            'empresa' => $empresa,
            'logoDataUri' => $this->logoDataUri($empresa),
            'numeroNf' => ltrim((string) ($nfe->numero ?? ''), '0') ?: (string) ($nfe->numero ?? '—'),
            'clienteNome' => mb_strtoupper(trim((string) ($cliente?->nome_razao ?: '—')), 'UTF-8'),
            'remetenteNome' => mb_strtoupper(trim((string) (
                $empresa?->fantasia ?: $empresa?->razao_social ?: $empresa?->nome ?: '—'
            )), 'UTF-8'),
            'transportadoraNome' => mb_strtoupper(trim((string) (
                $nfe->transportadora?->apelido
                    ?: $nfe->transportadora?->proprietario
                    ?: '—'
            )), 'UTF-8'),
            'destino' => $destino !== '' ? $destino : '—',
            'volumes' => $volumes,
            'autoPrint' => $request->boolean('auto', true),
        ]);
    }

    private function logoDataUri(?Empresa $empresa): ?string
    {
        if (! $empresa || blank($empresa->logo_path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', (string) $empresa->logo_path), '/');
        $candidates = [
            Storage::disk('public')->path($path),
            storage_path('app/public/'.$path),
            public_path('storage/'.$path),
            public_path($path),
        ];

        foreach ($candidates as $absolute) {
            if (! is_string($absolute) || $absolute === '' || ! is_file($absolute)) {
                continue;
            }

            $contents = @file_get_contents($absolute);

            if ($contents === false || $contents === '') {
                continue;
            }

            $mime = @mime_content_type($absolute) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return null;
    }
}
