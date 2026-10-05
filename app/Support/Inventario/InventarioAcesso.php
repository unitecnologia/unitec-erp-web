<?php

namespace App\Support\Inventario;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class InventarioAcesso
{
    public const CONSULTA = 'inventario.access';

    public const CONTAGEM = 'inventario.contar';

    public const FINALIZAR = 'inventario.finalizar';

    public static function podeEntrar(?User $user): bool
    {
        if (! $user || ! $user->ativo || ! $user->podeAcessarApp(User::APP_INVENTARIO)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return self::pode($user, self::CONSULTA)
            || self::pode($user, self::CONTAGEM)
            || self::pode($user, self::FINALIZAR);
    }

    public static function pode(?User $user, string $permissao): bool
    {
        return \App\Support\Erp\ErpAccess::can($user, $permissao);
    }

    public static function exigir(?User $user, string $permissao): User
    {
        if (! $user instanceof User || ! self::podeEntrar($user) || ! self::pode($user, $permissao)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        return $user;
    }

    /**
     * Releitura no banco, sem o cache de permissões da sessão.
     */
    public static function exigirAgora(User $user, string $permissao): User
    {
        $fresh = User::query()->whereKey($user->getKey())->first();

        if (! $fresh || ! $fresh->ativo || ! $fresh->podeAcessarApp(User::APP_INVENTARIO)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        if ($fresh->is_admin) {
            return $fresh;
        }

        if (! in_array($permissao, $fresh->effectivePermissionKeys(), true)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        return $fresh;
    }

    /**
     * @return array{user: User, empresa: Empresa, estoque: Estoque}
     */
    public static function contexto(?User $user = null): array
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $user->ativo) {
            throw new InventarioException('Sessão inválida.');
        }

        ErpContext::clearMemo();
        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId === null || $empresaId <= 0 || ! ErpContext::userCanAccessEmpresa($empresaId, $user)) {
            throw new InventarioException('Empresa da sessão inválida. Selecione uma empresa liberada para continuar.');
        }

        $empresa = Empresa::query()->whereKey($empresaId)->where('ativo', true)->first();

        if (! $empresa) {
            throw new InventarioException('Empresa da sessão inválida. Selecione uma empresa liberada para continuar.');
        }

        $estoqueId = app(ProductEstoqueSaldoService::class)->estoqueIdParaEmpresa((int) $empresa->id);
        $estoque = $estoqueId
            ? Estoque::query()->whereKey($estoqueId)->where('empresa_id', $empresa->id)->where('ativo', true)->first()
            : null;

        if (! $estoque) {
            throw new InventarioException('Não há depósito ativo para a empresa da sessão.');
        }

        return [
            'user' => $user,
            'empresa' => $empresa,
            'estoque' => $estoque,
        ];
    }
}
