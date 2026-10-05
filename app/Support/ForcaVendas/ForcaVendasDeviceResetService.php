<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasDevice;
use App\Models\ForcaVendasDeviceReset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Reset da base local do app Força de Vendas, autorizado pelo retaguarda.
 * Não toca em pedidos/clientes já gravados no ERP.
 */
class ForcaVendasDeviceResetService
{
    public function tabelaDisponivel(): bool
    {
        return Schema::hasTable((new ForcaVendasDeviceReset)->getTable());
    }

    /**
     * Cria a autorização. Se já existir uma pendente para o aparelho, devolve a mesma
     * (o app executa uma vez só, então não faz sentido empilhar).
     *
     * @return array{reset: ForcaVendasDeviceReset, criado: bool}
     */
    public function autorizar(ForcaVendasDevice $device, ?User $autorizadoPor): array
    {
        return DB::transaction(function () use ($device, $autorizadoPor): array {
            $pendente = ForcaVendasDeviceReset::query()
                ->where('device_uuid', $device->device_uuid)
                ->where('status', ForcaVendasDeviceReset::STATUS_PENDENTE)
                ->lockForUpdate()
                ->first();

            if ($pendente !== null) {
                return ['reset' => $pendente, 'criado' => false];
            }

            $reset = ForcaVendasDeviceReset::query()->create([
                'uuid' => (string) Str::uuid(),
                'forca_vendas_device_id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'status' => ForcaVendasDeviceReset::STATUS_PENDENTE,
                'authorized_by' => $autorizadoPor?->id,
                'authorized_at' => now(),
            ]);

            return ['reset' => $reset, 'criado' => true];
        });
    }

    public function pendente(string $deviceUuid): ?ForcaVendasDeviceReset
    {
        if ($deviceUuid === '' || ! $this->tabelaDisponivel()) {
            return null;
        }

        return ForcaVendasDeviceReset::query()
            ->where('device_uuid', $deviceUuid)
            ->where('status', ForcaVendasDeviceReset::STATUS_PENDENTE)
            ->orderBy('id')
            ->first();
    }

    /**
     * Marca o reset como concluído pelo aparelho dono da autorização.
     * Repetir a confirmação do mesmo aparelho é idempotente (resposta perdida na rede).
     *
     * @return array{ok: bool, ja_concluido?: bool, erro?: string, reset?: ForcaVendasDeviceReset}
     */
    public function concluir(string $resetUuid, string $deviceUuid, ?string $appVersion = null): array
    {
        if ($resetUuid === '' || $deviceUuid === '') {
            return ['ok' => false, 'erro' => 'nao_encontrado'];
        }

        return DB::transaction(function () use ($resetUuid, $deviceUuid, $appVersion): array {
            $reset = ForcaVendasDeviceReset::query()
                ->where('uuid', $resetUuid)
                ->lockForUpdate()
                ->first();

            if ($reset === null || ! hash_equals((string) $reset->device_uuid, $deviceUuid)) {
                return ['ok' => false, 'erro' => 'nao_encontrado'];
            }

            if ($reset->status === ForcaVendasDeviceReset::STATUS_CONCLUIDO) {
                return ['ok' => true, 'ja_concluido' => true, 'reset' => $reset];
            }

            if (! $reset->isPendente()) {
                return ['ok' => false, 'erro' => 'status_invalido'];
            }

            $reset->forceFill([
                'status' => ForcaVendasDeviceReset::STATUS_CONCLUIDO,
                'completed_at' => now(),
                'completed_app_version' => $appVersion !== null ? Str::limit($appVersion, 40, '') : null,
            ])->save();

            $this->encerrarSessoesDoAparelho($deviceUuid);

            return ['ok' => true, 'ja_concluido' => false, 'reset' => $reset];
        });
    }

    /**
     * Último reset de cada aparelho (pendente tem prioridade) para a grade de Aparelhos.
     *
     * @param  list<string>  $deviceUuids
     * @return Collection<string, ForcaVendasDeviceReset>
     */
    public function resumoPorAparelho(array $deviceUuids): Collection
    {
        if ($deviceUuids === [] || ! $this->tabelaDisponivel()) {
            return collect();
        }

        return ForcaVendasDeviceReset::query()
            ->with('authorizer:id,name')
            ->whereIn('device_uuid', $deviceUuids)
            ->orderBy('id')
            ->get()
            ->groupBy('device_uuid')
            ->map(function (Collection $resets): ForcaVendasDeviceReset {
                return $resets->first(fn (ForcaVendasDeviceReset $r): bool => $r->isPendente())
                    ?? $resets->last();
            });
    }

    /**
     * O app já apagou o token local; invalida também no servidor as sessões
     * abertas por este aparelho e libera o vínculo do vendedor (o próximo login
     * válido vincula de novo). Autorização, empresa e terminal continuam.
     */
    private function encerrarSessoesDoAparelho(string $deviceUuid): void
    {
        $tokenModel = Sanctum::personalAccessTokenModel();
        $tokenModel::query()->where('name', 'fv:'.$deviceUuid)->delete();

        ForcaVendasDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->update(['current_token_id' => null, 'user_id' => null]);
    }
}
