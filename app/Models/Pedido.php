<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pedido / DAV (Monitor de Vendas). Separado de Orçamento.
 */
#[Fillable([
    'numero',
    'data',
    'hora',
    'cliente_id',
    'cliente_nome',
    'cliente_cpf_cnpj',
    'cliente_endereco',
    'cliente_numero',
    'cliente_bairro',
    'cliente_cep',
    'cliente_cidade',
    'cliente_uf',
    'cliente_fone',
    'cliente_whatsapp',
    'vendedor_id',
    'subtotal',
    'percentual_desconto',
    'desconto_valor',
    'forma_pagamento',
    'validade_dias',
    'observacoes',
    'total',
    'status',
    'plataforma',
])]
class Pedido extends Model
{
    public const STATUS_ABERTO = 'aberto';

    public const STATUS_FECHADO = 'fechado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_IMPORTADO = 'importado';

    public const PLATAFORMA_FV = 'fv';

    public const PLATAFORMA_ERP = 'erp';

    protected $table = 'pedidos';

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'subtotal' => 'decimal:2',
            'percentual_desconto' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'total' => 'decimal:2',
            'validade_dias' => 'integer',
        ];
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function horaExibicao(): ?string
    {
        if (filled($this->hora)) {
            return substr((string) $this->hora, 0, 5);
        }

        return $this->created_at?->format('H:i');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function clienteDisplayNome(): string
    {
        if (filled($this->cliente_nome)) {
            return mb_strtoupper(trim((string) $this->cliente_nome), 'UTF-8');
        }

        return mb_strtoupper((string) ($this->cliente?->nome_razao ?? ''), 'UTF-8');
    }

    public function clienteDisplayCpfCnpj(): string
    {
        return filled($this->cliente_cpf_cnpj)
            ? (string) $this->cliente_cpf_cnpj
            : (string) ($this->cliente?->cpf_cnpj ?? '');
    }

    public function clienteDisplayWhatsapp(): string
    {
        if (filled($this->cliente_whatsapp)) {
            return (string) $this->cliente_whatsapp;
        }

        return (string) ($this->cliente?->celular1 ?: ($this->cliente?->fone1 ?? ''));
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class)->orderBy('item');
    }

    public function forcaVendasOrder(): HasOne
    {
        return $this->hasOne(ForcaVendasOrder::class, 'pedido_id');
    }
}
