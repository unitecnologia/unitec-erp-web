<?php

namespace App\Support\VendasInternas;

use App\Models\User;
use App\Models\VendasInternasDevice;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo fixo usuário ↔ aparelho do Vendas Internas.
 *
 * `vendas_internas_devices.user_id` é o vínculo: vazio = aparelho livre (o primeiro
 * login válido grava); preenchido = só esse usuário autentica. Só o Reset da
 * Base concluído (VendasInternasDeviceResetService) volta para vazio.
 */
class VendasInternasDeviceVinculo
{
    public const CODE_OUTRO_USUARIO = 'device_vinculado_outro';

    public const MSG_OUTRO_USUARIO = 'Este aparelho está vinculado a outro usuário. Solicite o Reset da Base no ERP.';

    /**
     * Valida o login no aparelho e grava o vínculo se o aparelho estiver livre.
     * Retorna o código de recusa ou null quando o usuário pode entrar.
     */
    public function vincularOuRecusar(VendasInternasDevice $device, User $user): ?string
    {
        return DB::transaction(function () use ($device, $user): ?string {
            $atual = VendasInternasDevice::query()->whereKey($device->id)->lockForUpdate()->first();

            if ($atual === null) {
                return self::CODE_OUTRO_USUARIO;
            }

            if ($atual->user_id === null) {
                $atual->forceFill(['user_id' => $user->id])->save();
                $device->setAttribute('user_id', $user->id);

                return null;
            }

            return (int) $atual->user_id === (int) $user->id ? null : self::CODE_OUTRO_USUARIO;
        });
    }

    /**
     * Requisição autenticada: o dono do token precisa ser o usuário vinculado.
     */
    public function recusaParaSessao(VendasInternasDevice $device, User $user): ?string
    {
        if ($device->user_id === null || (int) $device->user_id !== (int) $user->id) {
            return self::CODE_OUTRO_USUARIO;
        }

        return null;
    }

    public static function mensagem(string $code): string
    {
        return self::MSG_OUTRO_USUARIO;
    }
}
