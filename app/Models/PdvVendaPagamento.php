<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaPagamento extends Model
{
    protected $table = 'pdv_venda_pagamentos';

    protected $fillable = [
        'pdv_venda_id',
        'forma',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }
}
