<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'devolucao_compra_id',
    'codigo_legado',
    'item',
    'product_id',
    'compra_item_id',
    'produto_codigo',
    'produto_descricao',
    'qtd',
    'qtd_comprada',
    'preco',
    'total',
])]
class DevolucaoCompraItem extends Model
{
    protected $table = 'devolucao_compra_itens';

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(DevolucaoCompra::class, 'devolucao_compra_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function compraItem(): BelongsTo
    {
        return $this->belongsTo(CompraItem::class);
    }

    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:3',
            'qtd_comprada' => 'decimal:3',
            'preco' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }
}
