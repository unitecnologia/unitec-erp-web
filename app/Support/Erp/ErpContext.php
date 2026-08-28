<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ErpContext
{
    private static ?int $memoEmpresaId = null;

    private static bool $memoEmpresaIdResolved = false;

    private static ?Empresa $memoEmpresa = null;

    private static bool $memoEmpresaResolved = false;

    /**
     * Empresa ativa da sessão (com fallback para a empresa do usuário).
     *
     * Não faz fallback para "primeira empresa ativa": em ambiente multi-empresa
     * isso vazaria dados de outro tenant. Se não houver sessão nem empresa do
     * usuário, retorna null e o chamador deve tratar (ver requireEmpresaId()).
     */
    public static function currentEmpresaId(): ?int
    {
        if (self::$memoEmpresaIdResolved) {
            return self::$memoEmpresaId;
        }

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);
        self::$memoEmpresaId = filled($empresaId) ? (int) $empresaId : null;
        self::$memoEmpresaIdResolved = true;

        return self::$memoEmpresaId;
    }

    public static function currentEmpresa(): ?Empresa
    {
        if (self::$memoEmpresaResolved) {
            return self::$memoEmpresa;
        }

        $empresaId = self::currentEmpresaId();
        self::$memoEmpresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        self::$memoEmpresaResolved = true;

        return self::$memoEmpresa;
    }

    /**
     * Limpa memo do request (troca de empresa / testes).
     */
    public static function clearMemo(): void
    {
        self::$memoEmpresaId = null;
        self::$memoEmpresaIdResolved = false;
        self::$memoEmpresa = null;
        self::$memoEmpresaResolved = false;
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
            'Versão' => ErpUpdateService::readInstalledVersion(),
        ];
    }
}
