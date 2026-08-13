<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Models\NfeCartaCorrecao;
use App\Support\Erp\Nfe\NfeCartaCorrecaoReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NfeCartaCorrecaoReportController
{
    public function __invoke(
        Request $request,
        NfeCartaCorrecao $carta,
        NfeCartaCorrecaoReportService $service,
    ): View {
        $user = Auth::user();

        abort_unless($user, 403);

        $carta->load('nfe');

        abort_unless(
            $carta->nfe !== null && filled($carta->protocolo),
            404,
        );

        $empresaId = session('erp_empresa_id', $user->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user->empresa;

        if ($carta->nfe->empresa_id) {
            abort_unless(
                (int) $carta->nfe->empresa_id === (int) ($empresaId ?? $user->empresa_id),
                403,
            );
        }

        return view(
            'reports.nfe-carta-correcao',
            $service->buildViewData(
                carta: $carta,
                empresa: $empresa,
                autoPrint: $request->boolean('auto'),
            ),
        );
    }
}
