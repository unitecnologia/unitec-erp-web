<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'ultimo_preco',
    'preco_custo',
    'preco_atacado',
    'preco_especial',
    'registrado_em',
    'usuario',
    'forma_alteracao',
])]
class ProductPriceHistory extends Model
{
    protected function casts(): array
    {
        return [
            'ultimo_preco' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'preco_atacado' => 'decimal:2',
            'preco_especial' => 'decimal:2',
            'registrado_em' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
