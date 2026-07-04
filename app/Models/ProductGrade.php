<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'descricao',
    'tamanho',
    'qtd',
    'preco',
    'preco_atacado',
])]
class ProductGrade extends Model
{
    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:3',
            'preco' => 'decimal:2',
            'preco_atacado' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
