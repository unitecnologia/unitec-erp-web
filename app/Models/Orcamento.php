<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'numero',
    'data',
    'cliente_id',
    'vendedor_id',
    'subtotal',
    'percentual_desconto',
    'desconto_valor',
    'forma_pagamento',
    'validade_dias',
    'observacoes',
    'total',
    'status',
])]
class Orcamento extends Model
{
    public const STATUS_ABERTO = 'aberto';

    public const STATUS_FECHADO = 'fechado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_IMPORTADO = 'importado';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTO => 'Aberto',
            self::STATUS_FECHADO => 'Fechado',
            self::STATUS_CANCELADO => 'Cancelado',
            self::STATUS_IMPORTADO => 'Importado',
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

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function forcaVendasOrder(): HasOne
    {
        return $this->hasOne(ForcaVendasOrder::class, 'orcamento_id');
    }

    /**
     * Orçamentos visíveis na tela Orçamentos do ERP.
     * Pedidos do app (tipo "pedido") ficam apenas no Monitor de Vendas.
     */
    public function scopeVisivelNaListaOrcamentos(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'forcaVendasOrder',
            fn (Builder $q) => $q->where('tipo', ForcaVendasOrder::TIPO_PEDIDO),
        );
    }

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
}
