<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'outras_saida_movimento_id',
    'item',
    'product_id',
    'produto_codigo',
    'produto_descricao',
    'qtd',
    'preco',
    'total',
])]
class OutrasSaidaMovimentoItem extends Model
{
    protected $table = 'outras_saida_movimento_itens';

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(OutrasSaidaMovimento::class, 'outras_saida_movimento_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:3',
            'preco' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }
}
