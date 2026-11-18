<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'receita_id',
    'component_product_id',
    'quantidade',
])]
class ProductReceitaItem extends Model
{
    protected $table = 'product_receita_itens';

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
        ];
    }

    public function receita(): BelongsTo
    {
        return $this->belongsTo(ProductReceita::class, 'receita_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
