<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'data',
    'product_id',
    'qtd_ajust',
])]
class AjusteEstoque extends Model
{
    protected $table = 'ajustes_estoque';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'qtd_ajust' => 'decimal:3',
        ];
    }
}
