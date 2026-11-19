<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueios de edição em forca_vendas_orders (reabertura pelo App Força de Vendas).
 *
 * - EM EDIÇÃO NO FV: não expira por tempo; liberado por unlock/sync.
 * - EM EDIÇÃO NO ERP: liberado ao sair/gravar na Tela de Venda; TTL protege sessão abandonada.
 */
class ForcaVendasEdicaoLockService
{
    public const LABEL = 'EM EDIÇÃO NO FV';

    public const LABEL_ERP = 'EM EDIÇÃO NO ERP';

    public const CODE_LOCKED = 'fv_em_edicao';

    public const CODE_LOCKED_OTHER = 'fv_em_edicao_outro_aparelho';

    public const CODE_LOCKED_ERP = 'erp_em_edicao';

    public const MSG_ERP = 'Documento em edição no Força de Vendas (EM EDIÇÃO NO FV). Aguarde a sincronização ou o cancelamento da edição no aplicativo.';

    public const MSG_APP = 'Pedido em edição no ERP';

    public const ERP_TTL_MINUTES = 30;

    private static ?bool $colunasOk = null;

    private static ?bool $colunasErpOk = null;

    public function colunasDisponiveis(): bool
    {
        if (self::$colunasOk !== null) {
            return self::$colunasOk;
        }

        $table = (new ForcaVendasOrder)->getTable();

        return self::$colunasOk = Schema::hasTable($table)
            && Schema::hasColumn($table, 'em_edicao_fv');
    }

    public function colunasErpDisponiveis(): bool
    {
        if (self::$colunasErpOk !== null) {
            return self::$colunasErpOk;
        }

        $table = (new ForcaVendasOrder)->getTable();

        return self::$colunasErpOk = Schema::hasTable($table)
            && Schema::hasColumn($table, 'em_edicao_erp');
    }

    public function estaEmEdicao(?ForcaVendasOrder $order): bool
    {
        return $order !== null
            && $this->colunasDisponiveis()
            && (bool) $order->em_edicao_fv;
    }

    public function estaEmEdicaoErp(?ForcaVendasOrder $order, bool $respeitarTtl = true): bool
    {
        if ($order === null || ! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return false;
        }

        if ($respeitarTtl && $this->erpLockExpirado($order)) {
            return false;
        }

        return true;
    }

    /**
     * @throws \RuntimeException
     */
    public function assertNaoEmEdicaoFv(?ForcaVendasOrder $order): void
    {
        if ($this->estaEmEdicao($order)) {
            throw new \RuntimeException(self::MSG_ERP);
        }
    }

    /**
     * @return array{ok: bool, code?: string, message?: string, label?: string, locked?: bool, ja_possuia?: bool}
     */
    public function adquirir(string $uuid, string $deviceUuid, User $user): array
    {
        if ($uuid === '' || $deviceUuid === '') {
            return ['ok' => false, 'code' => 'invalid', 'message' => 'Documento ou aparelho inválido.'];
        }

        if (! $this->colunasDisponiveis()) {
            return ['ok' => false, 'code' => 'unavailable', 'message' => 'Bloqueio de edição indisponível. Execute as migrações.'];
        }

        $empresaId = $user->empresa_id ? (int) $user->empresa_id : null;
        if (! EmpresaParametros::forcaVendasPermitirReabrirPedido($empresaId)) {
            return ['ok' => false, 'code' => 'param_off', 'message' => 'Reabertura de pedido não está habilitada nesta empresa.'];
        }

        return DB::transaction(function () use ($uuid, $deviceUuid, $user): array {
            $order = ForcaVendasOrder::query()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return ['ok' => false, 'code' => 'not_found', 'message' => 'Documento não encontrado no ERP.'];
            }

            try {
                app(ForcaVendasSyncService::class)->assertPodeReabrirPedidoPublico($order, $user);
            } catch (\RuntimeException $e) {
                return ['ok' => false, 'code' => 'blocked', 'message' => $e->getMessage()];
            }

            $this->limparErpSeExpirado($order);

            if ($this->estaEmEdicaoErp($order, respeitarTtl: false)) {
                return [
                    'ok' => false,
                    'code' => self::CODE_LOCKED_ERP,
                    'message' => self::MSG_APP,
                ];
            }

            if ($order->em_edicao_fv) {
                if (hash_equals((string) $order->em_edicao_device_uuid, $deviceUuid)) {
                    return [
                        'ok' => true,
                        'locked' => true,
                        'label' => self::LABEL,
                        'ja_possuia' => true,
                    ];
                }

                return [
                    'ok' => false,
                    'code' => self::CODE_LOCKED_OTHER,
                    'message' => 'Documento já está em edição em outro aparelho ('.self::LABEL.').',
                ];
            }

            $order->forceFill([
                'em_edicao_fv' => true,
                'em_edicao_fv_at' => now(),
                'em_edicao_device_uuid' => $deviceUuid,
                'em_edicao_user_id' => $user->id,
            ])->save();

            return [
                'ok' => true,
                'locked' => true,
                'label' => self::LABEL,
                'ja_possuia' => false,
            ];
        });
    }

    /**
     * @return array{ok: bool, code?: string, message?: string}
     */
    public function liberar(string $uuid, string $deviceUuid): array
    {
        if ($uuid === '' || $deviceUuid === '' || ! $this->colunasDisponiveis()) {
            return ['ok' => false, 'code' => 'invalid', 'message' => 'Não foi possível liberar a edição.'];
        }

        return DB::transaction(function () use ($uuid, $deviceUuid): array {
            $order = ForcaVendasOrder::query()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return ['ok' => true, 'message' => 'Documento já inexistente.'];
            }

            if (! $order->em_edicao_fv) {
                return ['ok' => true, 'message' => 'Edição já estava liberada.'];
            }

            if (! hash_equals((string) $order->em_edicao_device_uuid, $deviceUuid)) {
                return [
                    'ok' => false,
                    'code' => self::CODE_LOCKED_OTHER,
                    'message' => 'Somente o aparelho que iniciou a edição pode liberá-la.',
                ];
            }

            $this->limparLockFv($order);

            return ['ok' => true];
        });
    }

    public function liberarAposSync(ForcaVendasOrder $order): void
    {
        if (! $this->colunasDisponiveis() || ! $order->em_edicao_fv) {
            return;
        }

        $this->limparLockFv($order);
    }

    public function assertPodeAplicarReabertura(ForcaVendasOrder $order, string $deviceUuid): void
    {
        if (! $this->colunasDisponiveis()) {
            return;
        }

        $this->limparErpSeExpirado($order);

        if ($this->estaEmEdicaoErp($order, respeitarTtl: false)) {
            throw new \RuntimeException(self::MSG_APP);
        }

        if (! $order->em_edicao_fv) {
            throw new \RuntimeException(
                'Documento não está bloqueado para edição no Força de Vendas. Reabra o pedido com conexão ao ERP.'
            );
        }

        if ($deviceUuid === '' || ! hash_equals((string) $order->em_edicao_device_uuid, $deviceUuid)) {
            throw new \RuntimeException(
                'Documento em edição em outro aparelho ('.self::LABEL.'). Não é possível sincronizar alterações daqui.'
            );
        }
    }

    public function adquirirErp(ForcaVendasOrder $order, User $user): void
    {
        if (! $this->colunasErpDisponiveis()) {
            return;
        }

        DB::transaction(function () use ($order, $user): void {
            /** @var ForcaVendasOrder $locked */
            $locked = ForcaVendasOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->estaEmEdicao($locked)) {
                throw new \RuntimeException(self::MSG_ERP);
            }

            $this->limparErpSeExpirado($locked);

            if ($this->estaEmEdicaoErp($locked, respeitarTtl: false)) {
                if ((int) $locked->em_edicao_erp_user_id === (int) $user->id) {
                    $locked->forceFill(['em_edicao_erp_at' => now()])->save();

                    return;
                }

                throw new \RuntimeException(
                    'Documento já está em edição no ERP por outro usuário ('.self::LABEL_ERP.').'
                );
            }

            $locked->forceFill([
                'em_edicao_erp' => true,
                'em_edicao_erp_at' => now(),
                'em_edicao_erp_user_id' => $user->id,
            ])->save();
        });
    }

    public function liberarErp(ForcaVendasOrder $order, ?User $user = null): void
    {
        if (! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return;
        }

        DB::transaction(function () use ($order, $user): void {
            /** @var ForcaVendasOrder|null $locked */
            $locked = ForcaVendasOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! (bool) $locked->em_edicao_erp) {
                return;
            }

            if ($user !== null
                && (int) $locked->em_edicao_erp_user_id > 0
                && (int) $locked->em_edicao_erp_user_id !== (int) $user->id) {
                return;
            }

            $this->limparLockErp($locked);
        });
    }

    public function tocarErp(ForcaVendasOrder $order, User $user): void
    {
        if (! $this->colunasErpDisponiveis()) {
            return;
        }

        if (! $this->estaEmEdicaoErp($order, respeitarTtl: true)) {
            return;
        }

        if ((int) $order->em_edicao_erp_user_id !== (int) $user->id) {
            return;
        }

        $order->forceFill(['em_edicao_erp_at' => now()])->save();
    }

    /**
     * @return array{em_edicao_fv: bool, em_edicao_erp: bool}
     */
    public function flagsParaApp(?ForcaVendasOrder $order): array
    {
        if ($order === null) {
            return ['em_edicao_fv' => false, 'em_edicao_erp' => false];
        }

        $this->limparErpSeExpirado($order);

        return [
            'em_edicao_fv' => $this->estaEmEdicao($order),
            'em_edicao_erp' => $this->estaEmEdicaoErp($order, respeitarTtl: false),
        ];
    }

    private function erpLockExpirado(ForcaVendasOrder $order): bool
    {
        if (! (bool) $order->em_edicao_erp) {
            return false;
        }

        $at = $order->em_edicao_erp_at;
        if ($at === null) {
            return true;
        }

        return $at->lte(now()->subMinutes(self::ERP_TTL_MINUTES));
    }

    private function limparErpSeExpirado(ForcaVendasOrder $order): void
    {
        if (! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return;
        }

        if (! $this->erpLockExpirado($order)) {
            return;
        }

        $this->limparLockErp($order);
    }

    private function limparLockFv(ForcaVendasOrder $order): void
    {
        $order->forceFill([
            'em_edicao_fv' => false,
            'em_edicao_fv_at' => null,
            'em_edicao_device_uuid' => null,
            'em_edicao_user_id' => null,
        ])->save();
    }

    private function limparLockErp(ForcaVendasOrder $order): void
    {
        $order->forceFill([
            'em_edicao_erp' => false,
            'em_edicao_erp_at' => null,
            'em_edicao_erp_user_id' => null,
        ])->save();
    }
}
