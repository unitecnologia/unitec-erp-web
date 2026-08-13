<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEstoqueSaldo extends Model
{
    protected $table = 'product_estoque_saldos';

    protected $fillable = [
        'product_id',
        'estoque_id',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }
}
