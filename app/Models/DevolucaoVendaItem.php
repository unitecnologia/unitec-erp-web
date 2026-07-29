<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'devolucao_venda_id',
    'codigo_legado',
    'item',
    'product_id',
    'venda_item_id',
    'produto_codigo',
    'produto_descricao',
    'qtd',
    'qtd_vendida',
    'preco',
    'total',
    'grade_legado',
])]
class DevolucaoVendaItem extends Model
{
    protected $table = 'devolucao_venda_itens';

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(DevolucaoVenda::class, 'devolucao_venda_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendaItem(): BelongsTo
    {
        return $this->belongsTo(VendaItem::class);
    }

    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:3',
            'qtd_vendida' => 'decimal:3',
            'preco' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }
}
