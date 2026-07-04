<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'component_product_id',
    'quantidade',
    'preco',
    'total',
])]
class ProductComposition extends Model
{
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
