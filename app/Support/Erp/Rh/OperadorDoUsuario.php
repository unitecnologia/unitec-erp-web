<?php

namespace App\Support\Erp\Rh;

use App\Models\RhFuncionario;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve o operador (vendedor) do usuário na empresa logada.
 * Um usuário pode ter um vínculo RH/operador por empresa; users.vendedor_id é só fallback.
 */
final class OperadorDoUsuario
{
    public static function vendedorAtivo(?User $user, ?int $empresaId = null): ?Vendedor
    {
        if ($user === null) {
            return null;
        }

        $empresaId = (int) ($empresaId ?: ErpContext::currentEmpresaId() ?: 0);

        if ($empresaId > 0 && Schema::hasColumn('rh_funcionarios', 'user_id')) {
            $vendedorId = (int) (RhFuncionario::query()
                ->where('user_id', $user->getKey())
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->whereNotNull('vendedor_id')
                ->value('vendedor_id') ?? 0);

            if ($vendedorId > 0) {
                $vendedor = Vendedor::query()
                    ->whereKey($vendedorId)
                    ->where('ativo', true)
                    ->first();

                if ($vendedor) {
                    return $vendedor;
                }
            }
        }

        // Fallback users.vendedor_id: só com empresa definida e vínculo explícito a ela.
        // Sem empresa (ou vendedor de outra): nunca retorna operador alheio.
        if ($empresaId <= 0) {
            return null;
        }

        $vendedor = $user->relationLoaded('vendedor')
            ? $user->vendedor
            : $user->vendedor()->first();

        if (! $vendedor || ! $vendedor->ativo) {
            return null;
        }

        if (! $vendedor->empresas()->where('empresas.id', $empresaId)->exists()) {
            return null;
        }

        return $vendedor;
    }
}
