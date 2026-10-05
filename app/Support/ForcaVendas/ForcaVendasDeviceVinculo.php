<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasDevice;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo fixo vendedor ↔ aparelho do Força de Vendas.
 *
 * `forca_vendas_devices.user_id` é o vínculo: vazio = aparelho livre (o primeiro
 * login válido grava); preenchido = só esse usuário autentica. Só o Reset da
 * Base concluído (ForcaVendasDeviceResetService) volta para vazio.
 */
class ForcaVendasDeviceVinculo
{
    public const CODE_OUTRO_VENDEDOR = 'device_vinculado_outro';

    public const CODE_SEM_VENDEDOR = 'user_sem_vendedor';

    public const MSG_OUTRO_VENDEDOR = 'Este aparelho está vinculado a outro vendedor.';

    public const MSG_SEM_VENDEDOR = 'Usuário sem vendedor vinculado no ERP.';

    public function usuarioTemVendedorValido(User $user): bool
    {
        $vendedorId = (int) ($user->vendedor_id ?? 0);

        if ($vendedorId <= 0) {
            return false;
        }

        return Vendedor::query()
            ->whereKey($vendedorId)
            ->where(fn ($q) => $q->where('ativo', true)->orWhereNull('ativo'))
            ->exists();
    }

    /**
     * Valida o login no aparelho e grava o vínculo se o aparelho estiver livre.
     * Retorna o código de recusa ou null quando o usuário pode entrar.
     */
    public function vincularOuRecusar(ForcaVendasDevice $device, User $user): ?string
    {
        if (! $this->usuarioTemVendedorValido($user)) {
            return self::CODE_SEM_VENDEDOR;
        }

        return DB::transaction(function () use ($device, $user): ?string {
            $atual = ForcaVendasDevice::query()->whereKey($device->id)->lockForUpdate()->first();

            if ($atual === null) {
                return self::CODE_OUTRO_VENDEDOR;
            }

            if ($atual->user_id === null) {
                $atual->forceFill(['user_id' => $user->id])->save();
                $device->setAttribute('user_id', $user->id);

                return null;
            }

            return (int) $atual->user_id === (int) $user->id ? null : self::CODE_OUTRO_VENDEDOR;
        });
    }

    /**
     * Requisição autenticada (token já emitido): o dono do token precisa ser o
     * vendedor vinculado ao aparelho. Tokens antigos de outros usuários caem aqui.
     */
    public function recusaParaSessao(ForcaVendasDevice $device, User $user): ?string
    {
        if (! $this->usuarioTemVendedorValido($user)) {
            return self::CODE_SEM_VENDEDOR;
        }

        if ($device->user_id === null || (int) $device->user_id !== (int) $user->id) {
            return self::CODE_OUTRO_VENDEDOR;
        }

        return null;
    }

    public static function mensagem(string $code): string
    {
        return $code === self::CODE_SEM_VENDEDOR ? self::MSG_SEM_VENDEDOR : self::MSG_OUTRO_VENDEDOR;
    }
}
