<?php

namespace App\Support\Erp\Tef\Contracts;

use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\Data\TefChargeCommand;
use App\Support\Erp\Tef\Data\TefProviderResult;

/**
 * Contrato comum dos provedores TEF eletrônicos (SuperTEF, Scope, simulador).
 *
 * Status normalizados: os mesmos de {@see TefIntencao}.
 * Adaptadores NÃO devem gravar dados sensíveis de cartão (PAN/CVV/track).
 */
interface TefElectronicProvider
{
    public function id(): string;

    public function label(): string;

    /** Provedor pode operar cobranças reais neste ambiente/instalação. */
    public function isOperational(): bool;

    /** Terminal pronto (credenciais + flags + dependências). */
    public function terminalReady(?Terminal $terminal): bool;

    /**
     * Cria intenção local ANTES de qualquer chamada externa.
     *
     * @param  array{
     *   empresa_id: int,
     *   terminal: Terminal,
     *   amount: float,
     *   caixa_sessao_id?: int|null,
     *   cupom_fingerprint?: string|null,
     *   transaction_type?: string,
     *   installment_count?: int,
     *   installment_type?: int,
     *   order_id?: string|null
     * }  $input
     */
    public function createIntention(array $input): TefIntencao;

    /** Envia cobrança uma única vez (idempotente se já enviada). */
    public function sendCharge(TefIntencao $intention, Terminal $terminal, ?TefChargeCommand $command = null): TefProviderResult;

    /** Consulta status / recupera pendente sem reenviar cobrança. */
    public function poll(TefIntencao $intention, Terminal $terminal): TefProviderResult;

    /** Cancela/desfaz cobrança pendente quando o provedor permitir. */
    public function cancel(TefIntencao $intention, Terminal $terminal): TefProviderResult;

    /** Diagnóstico leve (sem cobrança). */
    public function diagnose(Terminal $terminal): TefProviderResult;
}
