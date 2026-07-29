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
    'hora',
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
    'plataforma',
])]
class Orcamento extends Model
{
    public const STATUS_ABERTO = 'aberto';

    public const STATUS_FECHADO = 'fechado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_IMPORTADO = 'importado';

    public const PLATAFORMA_ERP = 'erp';

    public const PLATAFORMA_FV = 'fv';

    public const PLATAFORMA_VI = 'vi';

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

    /**
     * @return array<string, string>
     */
    public static function plataformaLabels(): array
    {
        return [
            self::PLATAFORMA_ERP => 'ERP',
            self::PLATAFORMA_FV => 'FV',
            self::PLATAFORMA_VI => 'VI',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function plataformaEfetiva(): string
    {
        $plataforma = $this->plataforma;

        if (filled($plataforma)) {
            return (string) $plataforma;
        }

        if ($this->relationLoaded('forcaVendasOrder')) {
            if ($this->forcaVendasOrder !== null) {
                return self::PLATAFORMA_FV;
            }
        } elseif ($this->forcaVendasOrder()->exists()) {
            return self::PLATAFORMA_FV;
        }

        if ($this->relationLoaded('vendasInternasOrder')) {
            if ($this->vendasInternasOrder !== null) {
                return self::PLATAFORMA_VI;
            }
        } elseif ($this->vendasInternasOrder()->exists()) {
            return self::PLATAFORMA_VI;
        }

        return self::PLATAFORMA_ERP;
    }

    public function plataformaLabel(): string
    {
        $plataforma = $this->plataformaEfetiva();

        return self::plataformaLabels()[$plataforma]
            ?? mb_strtoupper($plataforma, 'UTF-8');
    }

    public function horaExibicao(): ?string
    {
        if (filled($this->hora)) {
            return substr((string) $this->hora, 0, 5);
        }

        return $this->created_at?->format('H:i');
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

    public function vendasInternasOrder(): HasOne
    {
        return $this->hasOne(VendasInternasOrder::class, 'orcamento_id');
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
