<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'product_id',
        'quantidade',
        'valor_item',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_item' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
