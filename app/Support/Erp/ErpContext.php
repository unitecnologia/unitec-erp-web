<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ErpContext
{
    /**
     * Empresa ativa da sessão (com fallback para a empresa do usuário).
     *
     * Não faz fallback para "primeira empresa ativa": em ambiente multi-empresa
     * isso vazaria dados de outro tenant. Se não houver sessão nem empresa do
     * usuário, retorna null e o chamador deve tratar (ver requireEmpresaId()).
     */
    public static function currentEmpresaId(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return filled($empresaId) ? (int) $empresaId : null;
    }

    public static function currentEmpresa(): ?Empresa
    {
        $empresaId = self::currentEmpresaId();

        return $empresaId ? Empresa::query()->find($empresaId) : null;
    }

    /**
     * Igual a currentEmpresaId(), mas exige uma empresa selecionada.
     *
     * @throws \RuntimeException
     */
    public static function requireEmpresaId(): int
    {
        $empresaId = self::currentEmpresaId();

        if ($empresaId === null) {
            throw new \RuntimeException('Nenhuma empresa selecionada na sessão.');
        }

        return $empresaId;
    }

    /**
     * IDs de empresas que o usuário informado (ou o logado) pode acessar.
     *
     * @return list<int>
     */
    public static function accessibleEmpresaIds(?User $user = null): array
    {
        $user ??= Auth::user();

        return $user?->accessibleEmpresaIds() ?? [];
    }

    /**
     * O usuário (ou o logado) pode operar a empresa informada?
     */
    public static function userCanAccessEmpresa(int $empresaId, ?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->canAccessEmpresa($empresaId) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public static function statusBar(): array
    {
        $empresa = self::currentEmpresa() ?? Auth::user()?->empresa;

        return [
            'Tela' => 'Você está na tela de ' . ErpScreen::current(),
            'Empresa' => $empresa?->nome ?? '—',
            'IP' => request()->ip() ?? '—',
            'Atualizado Em' => ErpTimezone::toLocal()->format('d/m/Y H:i:s'),
            'Versão' => config('unitec.versao'),
        ];
    }
}
