<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'estoque_id',
    'quantidade',
    'forca_vendas_order_id',
    'orcamento_id',
    'orcamento_item_id',
    'vendedor_id',
    'vendedor_nome',
    'user_id',
    'empresa_id',
    'plataforma',
    'cliente_nome',
    'pedido_numero',
    'status',
    'consumida_at',
    'liberada_at',
])]
class EstoqueReserva extends Model
{
    public const STATUS_ATIVA = 'ativa';

    public const STATUS_CONSUMIDA = 'consumida';

    public const STATUS_LIBERADA = 'liberada';

    public const PLATAFORMA_MOBILE = 'mobile';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'consumida_at' => 'datetime',
            'liberada_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function forcaVendasOrder(): BelongsTo
    {
        return $this->belongsTo(ForcaVendasOrder::class);
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function situacaoLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONSUMIDA => 'Consumida (faturado)',
            self::STATUS_LIBERADA => 'Liberada',
            default => 'Ativa',
        };
    }
}
