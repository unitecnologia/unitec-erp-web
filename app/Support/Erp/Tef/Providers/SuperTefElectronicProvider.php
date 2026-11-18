<?php

namespace App\Support\Erp\Tef\Providers;

use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\Contracts\TefElectronicProvider;
use App\Support\Erp\Tef\Data\TefChargeCommand;
use App\Support\Erp\Tef\Data\TefProviderResult;
use App\Support\Erp\Tef\SuperTefPaymentService;
use App\Support\Erp\Tef\SuperTefPdvGate;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefProviderId;

/**
 * Adaptador do SuperTEF existente — delega ao {@see SuperTefPaymentService}
 * sem alterar regras de POST único, recuperação ou ocupação de POS.
 */
final class SuperTefElectronicProvider implements TefElectronicProvider
{
    public function __construct(
        private readonly ?SuperTefPaymentService $payments = null,
    ) {}

    public function id(): string
    {
        return TefProviderId::SUPERTEF;
    }

    public function label(): string
    {
        return TefProviderId::label(TefProviderId::SUPERTEF);
    }

    public function isOperational(): bool
    {
        return SuperTefPdvGate::featureEnabled();
    }

    public function terminalReady(?Terminal $terminal): bool
    {
        if ($terminal === null || ! $this->isOperational()) {
            return false;
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
        if (TefProviderId::normalize((string) $cfg['provedor']) !== TefProviderId::SUPERTEF) {
            return false;
        }

        return SuperTefPdvGate::terminalPronto($terminal);
    }

    public function createIntention(array $input): TefIntencao
    {
        $intention = $this->payments()->criarIntencao($input);

        if ($intention->provedor === null || $intention->provedor === '') {
            $intention->provedor = TefProviderId::SUPERTEF;
            $intention->save();
        }

        return $intention->fresh();
    }

    public function sendCharge(TefIntencao $intention, Terminal $terminal, ?TefChargeCommand $command = null): TefProviderResult
    {
        $res = $this->payments()->enviarCobranca($intention, $terminal);

        return new TefProviderResult(
            ok: ! $res['transport_error'] && in_array($res['intencao']->status, [
                TefIntencao::STATUS_PENDENTE,
                TefIntencao::STATUS_PAGA,
                TefIntencao::STATUS_POST_ENVIADO,
            ], true),
            message: (string) $res['message'],
            intention: $res['intencao'],
            transportError: (bool) $res['transport_error'],
            duplicatePrevented: str_contains(strtolower((string) $res['message']), 'já enviado'),
        );
    }

    public function poll(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        $updated = $this->payments()->consultar($intention, $terminal);

        return TefProviderResult::success(
            (string) ($updated->payment_message ?? 'Consulta SuperTEF'),
            $updated,
        );
    }

    public function cancel(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        $res = $this->payments()->cancelarPendente($intention, $terminal);

        return new TefProviderResult(
            ok: (bool) $res['ok'],
            message: (string) $res['message'],
            intention: $res['intencao'],
            meta: [
                'already_paid' => (bool) ($res['already_paid'] ?? false),
                'unknown' => (bool) ($res['unknown'] ?? false),
            ],
        );
    }

    public function diagnose(Terminal $terminal): TefProviderResult
    {
        if (! $this->isOperational()) {
            return TefProviderResult::notOperational('SuperTEF desabilitado neste ambiente (feature gate).');
        }

        if (! $this->terminalReady($terminal)) {
            return TefProviderResult::failure('Terminal SuperTEF incompleto (ativo, POS, chave e token SH).');
        }

        return TefProviderResult::success('SuperTEF operacional neste terminal.', meta: [
            'provider' => $this->id(),
            'poll_seconds' => SuperTefPdvGate::pollSeconds(),
        ]);
    }

    private function payments(): SuperTefPaymentService
    {
        return $this->payments ?? new SuperTefPaymentService();
    }
}
