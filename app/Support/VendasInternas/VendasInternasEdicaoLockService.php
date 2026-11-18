<?php

namespace App\Support\VendasInternas;

use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\User;
use App\Models\VendasInternasOrder;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueios de edição em vendas_internas_orders (somente documentos com vínculo VI).
 *
 * - EM EDIÇÃO NO VI: não expira por tempo; liberado por unlock/sync.
 * - EM EDIÇÃO NO ERP: liberado ao sair/gravar; TTL protege sessão abandonada.
 * Pedidos nativos do FV (sem linha VI) não são afetados.
 */
class VendasInternasEdicaoLockService
{
    public const LABEL = 'EM EDIÇÃO NO VI';

    public const LABEL_ERP = 'EM EDIÇÃO NO ERP';

    public const CODE_LOCKED = 'vi_em_edicao';

    public const CODE_LOCKED_OTHER = 'vi_em_edicao_outro_aparelho';

    public const CODE_LOCKED_ERP = 'erp_em_edicao';

    public const MSG_ERP = 'Documento em edição no Vendas Internas (EM EDIÇÃO NO VI). Aguarde a sincronização ou o cancelamento da edição no aplicativo.';

    /** Mensagem exibida no app VI quando o ERP segura o documento. */
    public const MSG_VI = 'Pedido em edição no ERP';

    /** Minutos sem atividade no ERP antes de considerar o bloqueio abandonado. */
    public const ERP_TTL_MINUTES = 30;

    private static ?bool $colunasOk = null;

    private static ?bool $colunasErpOk = null;

    public function colunasDisponiveis(): bool
    {
        if (self::$colunasOk !== null) {
            return self::$colunasOk;
        }

        $table = (new VendasInternasOrder)->getTable();

        return self::$colunasOk = Schema::hasTable($table)
            && Schema::hasColumn($table, 'em_edicao_vi');
    }

    public function colunasErpDisponiveis(): bool
    {
        if (self::$colunasErpOk !== null) {
            return self::$colunasErpOk;
        }

        $table = (new VendasInternasOrder)->getTable();

        return self::$colunasErpOk = Schema::hasTable($table)
            && Schema::hasColumn($table, 'em_edicao_erp');
    }

    public function estaEmEdicao(?VendasInternasOrder $order): bool
    {
        return $order !== null
            && $this->colunasDisponiveis()
            && (bool) $order->em_edicao_vi;
    }

    /**
     * ERP segura o documento (respeita TTL de abandono).
     */
    public function estaEmEdicaoErp(?VendasInternasOrder $order, bool $respeitarTtl = true): bool
    {
        if ($order === null || ! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return false;
        }

        if ($respeitarTtl && $this->erpLockExpirado($order)) {
            return false;
        }

        return true;
    }

    public function porForcaVendasOrderId(int $forcaVendasOrderId): ?VendasInternasOrder
    {
        if ($forcaVendasOrderId <= 0 || ! $this->colunasDisponiveis()) {
            return null;
        }

        return VendasInternasOrder::query()
            ->where('forca_vendas_order_id', $forcaVendasOrderId)
            ->first();
    }

    public function porOrcamentoId(int $orcamentoId): ?VendasInternasOrder
    {
        if ($orcamentoId <= 0 || ! $this->colunasDisponiveis()) {
            return null;
        }

        return VendasInternasOrder::query()
            ->where('orcamento_id', $orcamentoId)
            ->first();
    }

    /**
     * Bloqueia edição/faturamento no ERP se o documento VI estiver em edição.
     *
     * @throws \RuntimeException
     */
    public function assertNaoEmEdicaoVi(?VendasInternasOrder $order): void
    {
        if ($this->estaEmEdicao($order)) {
            throw new \RuntimeException(self::MSG_ERP);
        }
    }

    public function assertForcaVendasOrderLivre(ForcaVendasOrder $order): void
    {
        $this->assertNaoEmEdicaoVi($this->porForcaVendasOrderId((int) $order->id));
    }

    public function assertOrcamentoLivre(Orcamento $orcamento): void
    {
        $this->assertNaoEmEdicaoVi($this->porOrcamentoId((int) $orcamento->id));
    }

    /**
     * App solicita bloqueio para reabrir (exige documento sincronizado e elegível).
     *
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
        if (! EmpresaParametros::vendasInternasPermitirReabrirPedido($empresaId)) {
            return ['ok' => false, 'code' => 'param_off', 'message' => 'Reabertura de pedido não está habilitada nesta empresa.'];
        }

        return DB::transaction(function () use ($uuid, $deviceUuid, $user): array {
            $order = VendasInternasOrder::query()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return ['ok' => false, 'code' => 'not_found', 'message' => 'Documento não encontrado no ERP.'];
            }

            try {
                app(VendasInternasSyncService::class)->assertPodeReabrirPedidoPublico($order, $user);
            } catch (\RuntimeException $e) {
                return ['ok' => false, 'code' => 'blocked', 'message' => $e->getMessage()];
            }

            $this->limparErpSeExpirado($order);

            if ($this->estaEmEdicaoErp($order, respeitarTtl: false)) {
                return [
                    'ok' => false,
                    'code' => self::CODE_LOCKED_ERP,
                    'message' => self::MSG_VI,
                ];
            }

            if ($order->em_edicao_vi) {
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
                    'message' => 'Documento já está em edição em outro aparelho (EM EDIÇÃO NO VI).',
                ];
            }

            $order->forceFill([
                'em_edicao_vi' => true,
                'em_edicao_vi_at' => now(),
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
     * Cancela a edição no aparelho dono do bloqueio (confirmação no servidor).
     *
     * @return array{ok: bool, code?: string, message?: string}
     */
    public function liberar(string $uuid, string $deviceUuid): array
    {
        if ($uuid === '' || $deviceUuid === '' || ! $this->colunasDisponiveis()) {
            return ['ok' => false, 'code' => 'invalid', 'message' => 'Não foi possível liberar a edição.'];
        }

        return DB::transaction(function () use ($uuid, $deviceUuid): array {
            $order = VendasInternasOrder::query()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return ['ok' => true, 'message' => 'Documento já inexistente.'];
            }

            if (! $order->em_edicao_vi) {
                return ['ok' => true, 'message' => 'Edição já estava liberada.'];
            }

            if (! hash_equals((string) $order->em_edicao_device_uuid, $deviceUuid)) {
                return [
                    'ok' => false,
                    'code' => self::CODE_LOCKED_OTHER,
                    'message' => 'Somente o aparelho que iniciou a edição pode liberá-la.',
                ];
            }

            $this->limparLockVi($order);

            return ['ok' => true];
        });
    }

    /**
     * Após push de reabertura bem-sucedido: libera o bloqueio (qualquer aparelho dono da sync).
     */
    public function liberarAposSync(VendasInternasOrder $order): void
    {
        if (! $this->colunasDisponiveis() || ! $order->em_edicao_vi) {
            return;
        }

        $this->limparLockVi($order);
    }

    /**
     * Push de reabertura só é aceito com bloqueio VI do mesmo aparelho e sem lock ERP.
     */
    public function assertPodeAplicarReabertura(VendasInternasOrder $order, string $deviceUuid): void
    {
        if (! $this->colunasDisponiveis()) {
            return;
        }

        $this->limparErpSeExpirado($order);

        if ($this->estaEmEdicaoErp($order, respeitarTtl: false)) {
            throw new \RuntimeException(self::MSG_VI);
        }

        if (! $order->em_edicao_vi) {
            throw new \RuntimeException(
                'Documento não está bloqueado para edição no Vendas Internas. Reabra o pedido com conexão ao ERP.'
            );
        }

        if ($deviceUuid === '' || ! hash_equals((string) $order->em_edicao_device_uuid, $deviceUuid)) {
            throw new \RuntimeException(
                'Documento em edição em outro aparelho (EM EDIÇÃO NO VI). Não é possível sincronizar alterações daqui.'
            );
        }
    }

    /**
     * ERP abre edição de pedido VI-linked (Tela de Venda / Monitor).
     * No-op se não houver vínculo VI. Idempotente para o mesmo usuário.
     *
     * @throws \RuntimeException
     */
    public function adquirirErpPorForcaVendasOrderId(int $forcaVendasOrderId, User $user): void
    {
        $this->adquirirErpInterno(
            $this->porForcaVendasOrderId($forcaVendasOrderId),
            $user
        );
    }

    /**
     * ERP abre edição de orçamento VI-linked.
     *
     * @throws \RuntimeException
     */
    public function adquirirErpPorOrcamentoId(int $orcamentoId, User $user): void
    {
        $this->adquirirErpInterno(
            $this->porOrcamentoId($orcamentoId),
            $user
        );
    }

    public function liberarErpPorForcaVendasOrderId(int $forcaVendasOrderId, ?User $user = null): void
    {
        $this->liberarErpInterno(
            $this->porForcaVendasOrderId($forcaVendasOrderId),
            $user
        );
    }

    public function liberarErpPorOrcamentoId(int $orcamentoId, ?User $user = null): void
    {
        $this->liberarErpInterno(
            $this->porOrcamentoId($orcamentoId),
            $user
        );
    }

    /**
     * Renova o TTL enquanto o ERP continua editando (gravações intermediárias).
     */
    public function tocarErpPorForcaVendasOrderId(int $forcaVendasOrderId, User $user): void
    {
        $this->tocarErpInterno(
            $this->porForcaVendasOrderId($forcaVendasOrderId),
            $user
        );
    }

    public function tocarErpPorOrcamentoId(int $orcamentoId, User $user): void
    {
        $this->tocarErpInterno(
            $this->porOrcamentoId($orcamentoId),
            $user
        );
    }

    /**
     * Payload de pull: flags de bloqueio para o app.
     *
     * @return array{em_edicao_vi: bool, em_edicao_erp: bool}
     */
    public function flagsParaApp(?VendasInternasOrder $order): array
    {
        if ($order === null) {
            return ['em_edicao_vi' => false, 'em_edicao_erp' => false];
        }

        $this->limparErpSeExpirado($order);

        return [
            'em_edicao_vi' => $this->estaEmEdicao($order),
            'em_edicao_erp' => $this->estaEmEdicaoErp($order, respeitarTtl: false),
        ];
    }

    /**
     * @throws \RuntimeException
     */
    private function adquirirErpInterno(?VendasInternasOrder $order, User $user): void
    {
        if ($order === null || ! $this->colunasErpDisponiveis()) {
            return;
        }

        DB::transaction(function () use ($order, $user): void {
            /** @var VendasInternasOrder $locked */
            $locked = VendasInternasOrder::query()
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

    private function liberarErpInterno(?VendasInternasOrder $order, ?User $user): void
    {
        if ($order === null || ! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return;
        }

        DB::transaction(function () use ($order, $user): void {
            /** @var VendasInternasOrder|null $locked */
            $locked = VendasInternasOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! (bool) $locked->em_edicao_erp) {
                return;
            }

            // Só o dono libera (ou force sem user, p.ex. TTL já limpo / admin interno).
            if ($user !== null
                && (int) $locked->em_edicao_erp_user_id > 0
                && (int) $locked->em_edicao_erp_user_id !== (int) $user->id) {
                return;
            }

            $this->limparLockErp($locked);
        });
    }

    private function tocarErpInterno(?VendasInternasOrder $order, User $user): void
    {
        if ($order === null || ! $this->colunasErpDisponiveis()) {
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

    private function erpLockExpirado(VendasInternasOrder $order): bool
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

    private function limparErpSeExpirado(VendasInternasOrder $order): void
    {
        if (! $this->colunasErpDisponiveis() || ! (bool) $order->em_edicao_erp) {
            return;
        }

        if (! $this->erpLockExpirado($order)) {
            return;
        }

        $this->limparLockErp($order);
    }

    private function limparLockVi(VendasInternasOrder $order): void
    {
        $order->forceFill([
            'em_edicao_vi' => false,
            'em_edicao_vi_at' => null,
            'em_edicao_device_uuid' => null,
            'em_edicao_user_id' => null,
        ])->save();
    }

    private function limparLockErp(VendasInternasOrder $order): void
    {
        $order->forceFill([
            'em_edicao_erp' => false,
            'em_edicao_erp_at' => null,
            'em_edicao_erp_user_id' => null,
        ])->save();
    }
}
