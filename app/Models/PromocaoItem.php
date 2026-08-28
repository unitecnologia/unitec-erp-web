<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromocaoItem extends Model
{
    protected $table = 'promocao_itens';

    protected $fillable = [
        'promocao_id',
        'product_id',
        'preco_promocao',
        'mostrar_pdv',
    ];

    protected function casts(): array
    {
        return [
            'preco_promocao' => 'decimal:2',
            'mostrar_pdv' => 'boolean',
        ];
    }

    public function promocao(): BelongsTo
    {
        return $this->belongsTo(Promocao::class, 'promocao_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
