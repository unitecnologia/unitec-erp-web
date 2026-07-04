<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraItem extends Model
{
    protected $table = 'compra_itens';

    protected $fillable = [
        'compra_id',
        'product_id',
        'quantidade',
        'valor_unitario',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
