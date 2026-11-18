<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use App\Models\TefIntencao;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ciclo de vida comum das intenções TEF (provedor-agnóstico).
 * Regras: order_id único, post_enviado antes da chamada externa, sem reenvio automático,
 * vinculação à venda só com fingerprint, sem dados de cartão.
 */
final class TefIntentionLifecycle
{
    public function newOrderId(string $prefix = 'UNI-TEF'): string
    {
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '') {
            $prefix = 'UNI-TEF';
        }

        return $prefix.'-'.Str::upper((string) Str::ulid());
    }

    /**
     * @param  array{
     *   empresa_id: int,
     *   terminal: Terminal,
     *   provedor: string,
     *   amount: float,
     *   pos_id?: int|null,
     *   caixa_sessao_id?: int|null,
     *   cupom_fingerprint?: string|null,
     *   transaction_type?: string,
     *   installment_count?: int,
     *   installment_type?: int,
     *   order_id?: string|null,
     *   provider_external_id?: string|null
     * }  $input
     */
    public function create(array $input): TefIntencao
    {
        $terminal = $input['terminal'];
        if (! $terminal instanceof Terminal) {
            throw new RuntimeException('Terminal inválido para intenção TEF.');
        }

        $amount = round((float) $input['amount'], 2);
        if ($amount <= 0) {
            throw new RuntimeException('Valor TEF inválido.');
        }

        $provedor = TefProviderId::normalize((string) $input['provedor']);
        $orderId = trim((string) ($input['order_id'] ?? ''));
        if ($orderId === '') {
            $orderId = $this->newOrderId($provedor === TefProviderId::SCOPE ? 'UNI-SCOPE' : 'UNI-TEF');
        }

        $fingerprint = trim((string) ($input['cupom_fingerprint'] ?? ''));
        $posId = isset($input['pos_id']) && is_numeric($input['pos_id']) ? (int) $input['pos_id'] : 0;
        if ($posId < 0) {
            $posId = 0;
        }

        $attrs = [
            'empresa_id' => (int) $input['empresa_id'],
            'terminal_id' => (int) $terminal->id,
            'caixa_sessao_id' => isset($input['caixa_sessao_id']) ? (int) $input['caixa_sessao_id'] : null,
            'requer_reconciliacao' => false,
            'provedor' => $provedor,
            'order_id' => $orderId,
            'cupom_fingerprint' => $fingerprint !== '' ? $fingerprint : null,
            'provider_external_id' => $this->nullableString($input['provider_external_id'] ?? null),
            'pos_id' => $posId,
            'amount' => $amount,
            'transaction_type' => (string) ($input['transaction_type'] ?? '1'),
            'installment_count' => max(1, (int) ($input['installment_count'] ?? 1)),
            'installment_type' => max(1, (int) ($input['installment_type'] ?? 1)),
            'status' => TefIntencao::STATUS_CRIADA,
            'post_enviado' => false,
        ];

        return TefIntencao::query()->create($attrs);
    }

    /**
     * Marca envio ANTES da resposta externa — evita segunda cobrança em timeout.
     */
    public function markDispatchStarted(TefIntencao $intention): TefIntencao
    {
        $intention->refresh();

        if (! $intention->podeEnviarPost()) {
            return $intention;
        }

        $intention->post_enviado = true;
        $intention->post_enviado_em = now();
        $intention->status = TefIntencao::STATUS_POST_ENVIADO;
        $intention->save();

        return $intention->fresh();
    }

    public function markPending(TefIntencao $intention, ?string $message = null, ?string $externalId = null): TefIntencao
    {
        $intention->status = TefIntencao::STATUS_PENDENTE;
        if ($message !== null) {
            $intention->payment_message = mb_substr($message, 0, 191);
        }
        if ($externalId !== null && trim($externalId) !== '') {
            $intention->provider_external_id = trim($externalId);
        }
        $intention->save();

        return $intention->fresh();
    }

    public function markPaid(
        TefIntencao $intention,
        ?string $nsu = null,
        ?string $autorizacao = null,
        ?string $bandeira = null,
        ?string $message = null,
        ?array $raw = null,
    ): TefIntencao {
        $intention->status = TefIntencao::STATUS_PAGA;
        $intention->pago_em = $intention->pago_em ?? now();
        $intention->nsu = $this->nullableString($nsu) ?? $intention->nsu;
        $intention->autorizacao = $this->nullableString($autorizacao) ?? $intention->autorizacao;
        $intention->bandeira = $this->nullableString($bandeira) ?? $intention->bandeira;
        if ($message !== null) {
            $intention->payment_message = mb_substr($message, 0, 191);
        }
        if ($raw !== null) {
            $intention->raw_last = $this->sanitizeRaw($raw);
        }
        $intention->save();

        return $intention->fresh();
    }

    public function markFailed(TefIntencao $intention, string $message, ?array $raw = null): TefIntencao
    {
        $intention->status = TefIntencao::STATUS_FALHA;
        $intention->payment_message = mb_substr($message, 0, 191);
        if ($raw !== null) {
            $intention->raw_last = $this->sanitizeRaw($raw);
        }
        $intention->save();

        return $intention->fresh();
    }

    public function markIndeterminate(TefIntencao $intention, string $message): TefIntencao
    {
        $intention->status = TefIntencao::STATUS_INDETERMINADA;
        $intention->payment_message = mb_substr($message, 0, 191);
        $intention->save();

        return $intention->fresh();
    }

    public function markCancelled(TefIntencao $intention, string $message): TefIntencao
    {
        if ($intention->isPaga()) {
            return $intention;
        }

        $intention->status = TefIntencao::STATUS_CANCELADA;
        $intention->payment_message = mb_substr($message, 0, 191);
        $intention->save();

        return $intention->fresh();
    }

    public function linkToSale(TefIntencao $intention, int $pdvVendaId, ?int $pdvVendaPagamentoId = null): TefIntencao
    {
        if (! $intention->isPaga()) {
            throw new RuntimeException('Somente intenção paga pode vincular à venda.');
        }

        $intention->pdv_venda_id = $pdvVendaId;
        if ($pdvVendaPagamentoId !== null) {
            $intention->pdv_venda_pagamento_id = $pdvVendaPagamentoId;
        }
        $intention->save();

        return $intention->fresh();
    }

    /**
     * Bloqueia segunda tentativa aberta no mesmo terminal+provedor+slot.
     * slot = pos_id SuperTEF ou código de terminal Scope (hash numérico/0).
     */
    public function slotOccupied(
        Terminal $terminal,
        string $provedor,
        int $slotId,
        ?int $exceptIntentionId = null,
    ): bool {
        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('empresa_id', (int) $terminal->empresa_id)
            ->where('provedor', TefProviderId::normalize($provedor))
            ->where('pos_id', $slotId)
            ->where('requer_reconciliacao', false)
            ->where(function ($w): void {
                $w->whereIn('status', [
                    TefIntencao::STATUS_CRIADA,
                    TefIntencao::STATUS_POST_ENVIADO,
                    TefIntencao::STATUS_PENDENTE,
                    TefIntencao::STATUS_INDETERMINADA,
                ])->orWhere(function ($paga): void {
                    $paga->where('status', TefIntencao::STATUS_PAGA)
                        ->whereNull('pdv_venda_id');
                });
            });

        if ($exceptIntentionId) {
            $q->where('id', '!=', $exceptIntentionId);
        }

        return $q->exists();
    }

    public function findReusableOpen(
        Terminal $terminal,
        string $provedor,
        float $amount,
        string $cupomFingerprint,
        int $slotId,
        ?int $caixaSessaoId = null,
    ): ?TefIntencao {
        $fp = trim($cupomFingerprint);
        if ($fp === '') {
            return null;
        }

        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('empresa_id', (int) $terminal->empresa_id)
            ->where('provedor', TefProviderId::normalize($provedor))
            ->where('pos_id', $slotId)
            ->whereNull('pdv_venda_id')
            ->where('requer_reconciliacao', false)
            ->where('cupom_fingerprint', $fp)
            ->whereIn('status', [
                TefIntencao::STATUS_CRIADA,
                TefIntencao::STATUS_POST_ENVIADO,
                TefIntencao::STATUS_PENDENTE,
                TefIntencao::STATUS_INDETERMINADA,
            ])
            ->whereRaw('ABS(amount - ?) < 0.005', [round($amount, 2)])
            ->orderByDesc('id');

        if ($caixaSessaoId) {
            $q->where(function ($s) use ($caixaSessaoId): void {
                $s->where('caixa_sessao_id', $caixaSessaoId)
                    ->orWhereNull('caixa_sessao_id');
            });
        }

        return $q->first();
    }

    public function assertSameTerminal(TefIntencao $intention, Terminal $terminal): void
    {
        if ((int) $intention->terminal_id !== (int) $terminal->id
            || (int) $intention->empresa_id !== (int) $terminal->empresa_id) {
            throw new RuntimeException('Intenção TEF não pertence a este terminal/empresa.');
        }
    }

    /**
     * Remove chaves tipicamente sensíveis de cartão caso um adaptador as envie por engano.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function sanitizeRaw(array $raw): array
    {
        $blocked = [
            'pan', 'card_number', 'cardnumber', 'cvv', 'cvc', 'cvv2',
            'track1', 'track2', 'track_data', 'chip_data', 'pin', 'pinblock',
            'senha', 'password', 'emv_data',
        ];

        $out = [];
        foreach ($raw as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, $blocked, true)) {
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->sanitizeRaw($value);

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }
}
