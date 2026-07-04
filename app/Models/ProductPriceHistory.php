<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'ultimo_preco',
    'registrado_em',
    'usuario',
])]
class ProductPriceHistory extends Model
{
    protected function casts(): array
    {
        return [
            'ultimo_preco' => 'decimal:2',
            'registrado_em' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
