<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSerial extends Model
{
    protected $fillable = [
        'product_id',
        'numero_serie',
        'doc_saida',
        'situacao',
        'data_baixa',
    ];

    protected function casts(): array
    {
        return [
            'data_baixa' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
