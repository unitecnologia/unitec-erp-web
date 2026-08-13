<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImei extends Model
{
    protected $fillable = [
        'product_id',
        'fornecedor_id',
        'imei',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'fornecedor_id');
    }
}
