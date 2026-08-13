<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaPagamento extends Model
{
    protected $table = 'pdv_venda_pagamentos';

    protected $fillable = [
        'pdv_venda_id',
        'forma',
        'valor',
        'cartao_nsu',
        'cartao_autorizacao',
        'cartao_maquininha',
        'cartao_bandeira',
        'cartao_parcela',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }

    public function temCanhotoCartao(): bool
    {
        return filled($this->cartao_maquininha)
            || filled($this->cartao_bandeira)
            || filled($this->cartao_nsu)
            || filled($this->cartao_autorizacao)
            || filled($this->cartao_parcela);
    }

    /**
     * Forma + dados do canhoto para pedido/NFC-e.
     */
    public function descricaoComCanhoto(): string
    {
        $base = trim((string) $this->forma);

        if (! $this->temCanhotoCartao()) {
            return $base !== '' ? $base : '—';
        }

        $extra = array_values(array_filter([
            filled($this->cartao_maquininha) ? (string) $this->cartao_maquininha : null,
            filled($this->cartao_bandeira) ? (string) $this->cartao_bandeira : null,
            filled($this->cartao_parcela) ? (string) $this->cartao_parcela : null,
            filled($this->cartao_nsu) ? 'NSU '.$this->cartao_nsu : null,
            filled($this->cartao_autorizacao) ? 'AUT '.$this->cartao_autorizacao : null,
        ]));

        return $extra === []
            ? $base
            : trim($base.' ('.implode(' | ', $extra).')');
    }

    /**
     * Código tBand da NFC-e (01–09 / 99).
     */
    public function tBandFiscal(): ?string
    {
        $bandeira = mb_strtoupper(trim((string) $this->cartao_bandeira), 'UTF-8');

        if ($bandeira === '') {
            return null;
        }

        return match (true) {
            str_contains($bandeira, 'VISA') => '01',
            str_contains($bandeira, 'MASTER') => '02',
            str_contains($bandeira, 'AMERICAN') || str_contains($bandeira, 'AMEX') => '03',
            str_contains($bandeira, 'SOROCRED') => '04',
            str_contains($bandeira, 'DINERS') => '05',
            str_contains($bandeira, 'ELO') => '06',
            str_contains($bandeira, 'HIPER') => '07',
            str_contains($bandeira, 'AURA') => '08',
            str_contains($bandeira, 'CABAL') => '09',
            default => '99',
        };
    }
}
