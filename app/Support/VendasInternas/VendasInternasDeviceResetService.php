<?php

namespace App\Support\VendasInternas;

use App\Models\User;
use App\Models\VendasInternasDevice;
use App\Models\VendasInternasDeviceReset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Reset da base local do app Vendas Internas, autorizado pelo retaguarda.
 * Não toca em vendas/cadastros já gravados no ERP.
 */
class VendasInternasDeviceResetService
{
    public function tabelaDisponivel(): bool
    {
        return Schema::hasTable((new VendasInternasDeviceReset)->getTable());
    }

    /**
     * @return array{reset: VendasInternasDeviceReset, criado: bool}
     */
    public function autorizar(VendasInternasDevice $device, ?User $autorizadoPor): array
    {
        return DB::transaction(function () use ($device, $autorizadoPor): array {
            $pendente = VendasInternasDeviceReset::query()
                ->where('device_uuid', $device->device_uuid)
                ->where('status', VendasInternasDeviceReset::STATUS_PENDENTE)
                ->lockForUpdate()
                ->first();

            if ($pendente !== null) {
                return ['reset' => $pendente, 'criado' => false];
            }

            $reset = VendasInternasDeviceReset::query()->create([
                'uuid' => (string) Str::uuid(),
                'vendas_internas_device_id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'status' => VendasInternasDeviceReset::STATUS_PENDENTE,
                'authorized_by' => $autorizadoPor?->id,
                'authorized_at' => now(),
            ]);

            return ['reset' => $reset, 'criado' => true];
        });
    }

    public function pendente(string $deviceUuid): ?VendasInternasDeviceReset
    {
        if ($deviceUuid === '' || ! $this->tabelaDisponivel()) {
            return null;
        }

        return VendasInternasDeviceReset::query()
            ->where('device_uuid', $deviceUuid)
            ->where('status', VendasInternasDeviceReset::STATUS_PENDENTE)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{ok: bool, ja_concluido?: bool, erro?: string, reset?: VendasInternasDeviceReset}
     */
    public function concluir(string $resetUuid, string $deviceUuid, ?string $appVersion = null): array
    {
        if ($resetUuid === '' || $deviceUuid === '') {
            return ['ok' => false, 'erro' => 'nao_encontrado'];
        }

        return DB::transaction(function () use ($resetUuid, $deviceUuid, $appVersion): array {
            $reset = VendasInternasDeviceReset::query()
                ->where('uuid', $resetUuid)
                ->lockForUpdate()
                ->first();

            if ($reset === null || ! hash_equals((string) $reset->device_uuid, $deviceUuid)) {
                return ['ok' => false, 'erro' => 'nao_encontrado'];
            }

            if ($reset->status === VendasInternasDeviceReset::STATUS_CONCLUIDO) {
                return ['ok' => true, 'ja_concluido' => true, 'reset' => $reset];
            }

            if (! $reset->isPendente()) {
                return ['ok' => false, 'erro' => 'status_invalido'];
            }

            $reset->forceFill([
                'status' => VendasInternasDeviceReset::STATUS_CONCLUIDO,
                'completed_at' => now(),
                'completed_app_version' => $appVersion !== null ? Str::limit($appVersion, 40, '') : null,
            ])->save();

            $this->encerrarSessoesDoAparelho($deviceUuid);

            return ['ok' => true, 'ja_concluido' => false, 'reset' => $reset];
        });
    }

    /**
     * @param  list<string>  $deviceUuids
     * @return Collection<string, VendasInternasDeviceReset>
     */
    public function resumoPorAparelho(array $deviceUuids): Collection
    {
        if ($deviceUuids === [] || ! $this->tabelaDisponivel()) {
            return collect();
        }

        return VendasInternasDeviceReset::query()
            ->with('authorizer:id,name')
            ->whereIn('device_uuid', $deviceUuids)
            ->orderBy('id')
            ->get()
            ->groupBy('device_uuid')
            ->map(function (Collection $resets): VendasInternasDeviceReset {
                return $resets->first(fn (VendasInternasDeviceReset $r): bool => $r->isPendente())
                    ?? $resets->last();
            });
    }

    private function encerrarSessoesDoAparelho(string $deviceUuid): void
    {
        $tokenModel = Sanctum::personalAccessTokenModel();
        $tokenModel::query()->where('name', 'vi:'.$deviceUuid)->delete();

        VendasInternasDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->update(['current_token_id' => null, 'user_id' => null]);
    }
}
