<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id',
    'item',
    'product_id',
    'product_grade_id',
    'quantidade',
    'preco_unitario',
    'total',
    'desconto',
    'descricao',
])]
class PedidoItem extends Model
{
    protected $table = 'pedido_itens';

    protected function casts(): array
    {
        return [
            'item' => 'integer',
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'total' => 'decimal:2',
            'desconto' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(ProductGrade::class, 'product_grade_id');
    }
}
