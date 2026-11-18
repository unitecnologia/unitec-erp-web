<?php

namespace App\Support\Erp\Tef\Providers;

use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\Contracts\TefElectronicProvider;
use App\Support\Erp\Tef\Data\TefChargeCommand;
use App\Support\Erp\Tef\Data\TefProviderResult;
use App\Support\Erp\Tef\Scope\ScopeTefDiagnostico;
use App\Support\Erp\Tef\Scope\ScopeTefPdvGate;
use App\Support\Erp\Tef\TefProviderId;
use RuntimeException;

/**
 * Adaptador Scope TEF (Conexão Tech) — DESATIVADO até SDK/documentação oficial.
 *
 * Não inventa endpoints, assinaturas DLL nem respostas. Cobranças reais
 * são recusadas com mensagem clara; configuração e diagnóstico permanecem.
 */
final class ScopeTefElectronicProvider implements TefElectronicProvider
{
    public function id(): string
    {
        return TefProviderId::SCOPE;
    }

    public function label(): string
    {
        return TefProviderId::label(TefProviderId::SCOPE);
    }

    public function isOperational(): bool
    {
        return ScopeTefPdvGate::featureEnabled();
    }

    public function terminalReady(?Terminal $terminal): bool
    {
        return ScopeTefPdvGate::terminalPronto($terminal);
    }

    public function createIntention(array $input): TefIntencao
    {
        throw new RuntimeException($this->blockedMessage('criar intenção'));
    }

    public function sendCharge(TefIntencao $intention, Terminal $terminal, ?TefChargeCommand $command = null): TefProviderResult
    {
        return TefProviderResult::notOperational($this->blockedMessage('enviar cobrança'));
    }

    public function poll(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        return TefProviderResult::notOperational($this->blockedMessage('consultar pagamento'));
    }

    public function cancel(TefIntencao $intention, Terminal $terminal): TefProviderResult
    {
        return TefProviderResult::notOperational($this->blockedMessage('cancelar pagamento'));
    }

    public function diagnose(Terminal $terminal): TefProviderResult
    {
        return ScopeTefDiagnostico::run($terminal);
    }

    private function blockedMessage(string $acao): string
    {
        $reason = ScopeTefPdvGate::activationBlockedReason();

        return 'Scope TEF não pode '.$acao.'. '.$reason
            .' Ver tools/scope-tef-etapa1/README.md.';
    }
}
