<?php

namespace App\Support\Erp\Tef\Simulation;

use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\Contracts\TefElectronicProvider;
use App\Support\Erp\Tef\Data\TefChargeCommand;
use App\Support\Erp\Tef\Data\TefProviderResult;
use App\Support\Erp\Tef\TefIntentionLifecycle;
use App\Support\Erp\Tef\TefProviderId;
use RuntimeException;

/**
 * Provedor TEF 100% em memória/local para testes automatizados.
 * NÃO é Scope nem SuperTEF — não simula protocolo oficial Conexão Tech.
 *
 * Cenários via meta/command: approve, decline, timeout, disconnect,
 * fail_after_approve, pending_confirm, pending_undo.
 */
final class SimulatedTefProvider implements TefElectronicProvider
{
    public const PROVIDER_ID = 'simulated';

    /** @var array<string, string> order_id => scenario */
    private array $scenariosByOrder = [];

    /** @var array<string, int> */
    private array $sendCounts = [];

    public function __construct(
        private readonly TefIntentionLifecycle $lifecycle = new TefIntentionLifecycle(),
        private string $defaultScenario = 'approve',
    ) {}

    public function setDefaultScenario(string $scenario): void
    {
        $this->defaultScenario = $scenario;
    }

    public function setScenarioForOrder(string $orderId, string $scenario): void
    {
        $this->scenariosByOrder[$orderId] = $scenario;
    }

    public function sendCountFor(string $orderId): int
    {
        return (int) ($this->sendCounts[$orderId] ?? 0);
    }

    public function id(): string
    {
        return self::PROVIDER_ID;
    }

    public function label(): string
    {
        return 'Simulador TEF (testes)';
    }

    public function isOperational(): bool
    {
        return true;
    }

    public function terminalReady(?Terminal $terminal): bool
    {
        return $terminal !== null;
    }

    public function createIntention(array $input): TefIntencao
    {
        $terminal = $input['terminal'];
        if (! $terminal instanceof Terminal) {
            throw new RuntimeException('Terminal inválido.');
        }

        $slot = (int) ($input['pos_id'] ?? 1);
        if ($this->lifecycle->slotOccupied($terminal, self::PROVIDER_ID, $slot)) {
            throw new RuntimeException('Slot TEF simulado ocupado neste terminal.');
        }

        $input['provedor'] = self::PROVIDER_ID;
        $input['pos_id'] = $slot;

        return $this->lifecycle->create($input);
    }

    public function sendCharge(TefIntencao $intention, Terminal $terminal, ?TefChargeCommand $command = null): TefProviderResult
    {
        $this->lifecycle->assertSameTerminal($intention, $terminal);

        if (! $intention->podeEnviarPost()) {
            return new TefProviderResult(
                ok: true,
                message: 'POST já enviado — reenvio bloqueado (idempotência).',
                intention: $intention->fresh(),
                duplicatePrevented: true,
            );
        }

        $scenario = $this->resolveScenario($intention, $command);
        $this->sendCounts[$intention->order_id] = ($this->sendCounts[$intention->order_id] ?? 0) + 1;

        $intention = $this->lifecycle->markDispatchStarted($intention);

        return match ($scenario) {
            'timeout', 'disconnect' => $this->onTransportLoss($intention, $scenario),
            'decline' => $this->onDecline($intention),
            'fail_after_approve' => $this->onFailAfterApprove($intention),
            'pending_confirm', 'pending_undo' => $this->onPendingConfirm($intention, $scenario),
            default => $this->onApprove($intention),
        };
    }

    public function poll(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        $this->lifecycle->assertSameTerminal($intention, $terminal);
        $intention->last_poll_at = now();
        $intention->save();
        $intention = $intention->fresh();

        if ($intention->status === TefIntencao::STATUS_INDETERMINADA) {
            $scenario = $this->scenariosByOrder[$intention->order_id] ?? $this->defaultScenario;
            if (in_array($scenario, ['approve', 'fail_after_approve'], true)) {
                $intention = $this->lifecycle->markPaid(
                    $intention,
                    nsu: 'SIM-NSU',
                    autorizacao: 'SIM-AUT',
                    bandeira: 'VISA',
                    message: 'Recuperado após perda de conexão (simulado).',
                    raw: ['simulated' => true, 'recovered' => true],
                );

                if ($scenario === 'fail_after_approve') {
                    return TefProviderResult::failure(
                        'Aprovado no provedor, mas falha local pós-aprovação (simulado).',
                        $intention,
                    );
                }
            }
        }

        return TefProviderResult::success(
            (string) ($intention->payment_message ?? 'Poll simulado'),
            $intention,
        );
    }

    public function cancel(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        $this->lifecycle->assertSameTerminal($intention, $terminal);
        $intention->refresh();

        if ($intention->isPaga()) {
            return TefProviderResult::failure(
                'Já aprovado — cancelamento pendente não se aplica (simulado).',
                $intention,
            );
        }

        if ($intention->status === TefIntencao::STATUS_INDETERMINADA) {
            return new TefProviderResult(
                ok: false,
                message: 'Confirmação/desfazimento pendente após timeout (simulado).',
                intention: $intention,
                transportError: true,
                meta: ['pending_undo' => true],
            );
        }

        $intention = $this->lifecycle->markCancelled($intention, 'Cancelado (simulado).');

        return TefProviderResult::success('Cancelado (simulado).', $intention);
    }

    public function diagnose(Terminal $terminal): TefProviderResult
    {
        return TefProviderResult::success('Simulador TEF pronto.', meta: [
            'provider' => self::PROVIDER_ID,
            'terminal_id' => (int) $terminal->id,
            'empresa_id' => (int) $terminal->empresa_id,
        ]);
    }

    private function resolveScenario(TefIntencao $intention, ?TefChargeCommand $command): string
    {
        $fromMeta = (string) ($command?->meta['scenario'] ?? '');
        if ($fromMeta !== '') {
            $this->scenariosByOrder[$intention->order_id] = $fromMeta;

            return $fromMeta;
        }

        return $this->scenariosByOrder[$intention->order_id] ?? $this->defaultScenario;
    }

    private function onApprove(TefIntencao $intention): TefProviderResult
    {
        $intention = $this->lifecycle->markPaid(
            $intention,
            nsu: 'SIM-NSU',
            autorizacao: 'SIM-AUT',
            bandeira: 'VISA',
            message: 'Aprovado (simulado).',
            raw: ['simulated' => true, 'scenario' => 'approve'],
        );

        return TefProviderResult::success('Aprovado (simulado).', $intention);
    }

    private function onDecline(TefIntencao $intention): TefProviderResult
    {
        $intention = $this->lifecycle->markFailed($intention, 'Recusado (simulado).', [
            'simulated' => true,
            'scenario' => 'decline',
        ]);

        return TefProviderResult::failure('Recusado (simulado).', $intention);
    }

    private function onTransportLoss(TefIntencao $intention, string $scenario): TefProviderResult
    {
        $intention = $this->lifecycle->markIndeterminate(
            $intention,
            $scenario === 'timeout'
                ? 'Timeout de transporte (simulado). Não reenvie.'
                : 'Perda de conexão (simulado). Não reenvie.',
        );

        return new TefProviderResult(
            ok: false,
            message: (string) $intention->payment_message,
            intention: $intention,
            transportError: true,
        );
    }

    private function onFailAfterApprove(TefIntencao $intention): TefProviderResult
    {
        $intention = $this->lifecycle->markPaid(
            $intention,
            nsu: 'SIM-NSU-FA',
            autorizacao: 'SIM-AUT-FA',
            bandeira: 'ELO',
            message: 'Aprovado no provedor (simulado).',
            raw: ['simulated' => true, 'scenario' => 'fail_after_approve'],
        );

        return TefProviderResult::failure(
            'Falha local após aprovação — venda não persistida (simulado).',
            $intention,
        );
    }

    private function onPendingConfirm(TefIntencao $intention, string $scenario): TefProviderResult
    {
        $intention = $this->lifecycle->markPending(
            $intention,
            $scenario === 'pending_undo'
                ? 'Desfazimento pendente (simulado).'
                : 'Confirmação pendente (simulado).',
            externalId: 'SIM-EXT-'.$intention->id,
        );

        return TefProviderResult::success((string) $intention->payment_message, $intention, [
            'pending_confirm' => $scenario === 'pending_confirm',
            'pending_undo' => $scenario === 'pending_undo',
        ]);
    }
}
