<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;

class ErpContext
{
    /**
     * @return array<string, string>
     */
    public static function statusBar(): array
    {
        $user = Auth::user();
        $empresaId = session('erp_empresa_id', $user?->empresa_id);
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : $user?->empresa;

        return [
            'Tela' => 'Você está na tela de ' . ErpScreen::current(),
            'Empresa' => $empresa?->nome ?? '—',
            'Usuário' => $user?->name ?? '—',
            'IP' => request()->ip() ?? '—',
            'Atualizado Em' => now()->format('d/m/Y'),
            'Versão' => config('unitec.versao'),
            'Licenciado' => config('unitec.licenca'),
        ];
    }
}
