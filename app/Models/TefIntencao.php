<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TefIntencao extends Model
{
    public const STATUS_CRIADA = 'criada';

    public const STATUS_POST_ENVIADO = 'post_enviado';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PAGA = 'paga';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_ESTORNADA = 'estornada';

    public const STATUS_FALHA = 'falha';

    public const STATUS_INDETERMINADA = 'indeterminada';

    protected $table = 'tef_intencoes';

    protected $fillable = [
        'empresa_id',
        'terminal_id',
        'caixa_sessao_id',
        'pdv_venda_id',
        'pdv_venda_pagamento_id',
        'requer_reconciliacao',
        'ciencia_reconciliacao_em',
        'provedor',
        'order_id',
        'cupom_fingerprint',
        'payment_uniqueid',
        'provider_external_id',
        'pos_id',
        'amount',
        'transaction_type',
        'installment_count',
        'installment_type',
        'status',
        'post_enviado',
        'post_enviado_em',
        'last_poll_at',
        'pago_em',
        'nsu',
        'autorizacao',
        'bandeira',
        'acquirer_cnpj',
        'payment_message',
        'raw_last',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'post_enviado' => 'boolean',
            'requer_reconciliacao' => 'boolean',
            'ciencia_reconciliacao_em' => 'datetime',
            'post_enviado_em' => 'datetime',
            'last_poll_at' => 'datetime',
            'pago_em' => 'datetime',
            'raw_last' => 'array',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    public function pdvVenda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }

    public function isPaga(): bool
    {
        return $this->status === self::STATUS_PAGA;
    }

    public function isPendenteAberta(): bool
    {
        return in_array($this->status, [
            self::STATUS_CRIADA,
            self::STATUS_POST_ENVIADO,
            self::STATUS_PENDENTE,
            self::STATUS_INDETERMINADA,
        ], true);
    }

    public function podeEnviarPost(): bool
    {
        return ! $this->post_enviado && $this->status === self::STATUS_CRIADA;
    }

    public function requerReconciliacao(): bool
    {
        return (bool) $this->requer_reconciliacao;
    }

    /**
     * Operador já tomou ciência desta intenção órfã (aviso não se repete).
     * Não significa reconciliação administrativa concluída.
     */
    public function temCienciaReconciliacao(): bool
    {
        return $this->ciencia_reconciliacao_em !== null;
    }

    /**
     * Pagamento aprovado só pode fechar venda se o fingerprint do cupom bater
     * (ou se a intenção ainda é a da mesma operação Livewire em andamento).
     */
    /**
     * CNPJ da instituição de pagamento (adquirente/subadquirente) para NFC-e tpIntegra=1.
     * Preferência: coluna; fallback: payment_data.acquirer_cnpj em raw_last.
     * Exige 14 dígitos com DV válidos. Não usa CNPJ do emitente/softhouse nem inventa valor.
     */
    public function acquirerCnpjFiscal(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($this->acquirer_cnpj ?? '')) ?: '';
        if (self::isAcquirerCnpjValido($digits)) {
            return $digits;
        }

        $raw = is_array($this->raw_last) ? $this->raw_last : [];
        $data = is_array($raw['payment_data'] ?? null) ? $raw['payment_data'] : [];
        $fromRaw = preg_replace('/\D/', '', (string) ($data['acquirer_cnpj'] ?? '')) ?: '';

        return self::isAcquirerCnpjValido($fromRaw) ? $fromRaw : null;
    }

    /**
     * CNPJ fiscalmente utilizável no card (YA05-10): 14 dígitos + DV, não sequência repetida.
     */
    public static function isAcquirerCnpjValido(?string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', (string) ($cnpj ?? '')) ?: '';
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $digit1 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        if ((int) $cnpj[12] !== $digit1) {
            return false;
        }

        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $digit2 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        return (int) $cnpj[13] === $digit2;
    }

    public function podeVincularAoCupom(?string $cupomFingerprint): bool
    {
        if (! $this->isPaga() || filled($this->pdv_venda_id) || $this->requerReconciliacao()) {
            return false;
        }

        $fp = trim((string) ($this->cupom_fingerprint ?? ''));
        if ($fp === '') {
            // Legado/órfão sem vínculo inequívoco: nunca auto-associar.
            return false;
        }

        return hash_equals($fp, trim((string) ($cupomFingerprint ?? '')));
    }
}
