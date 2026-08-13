<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entrega_id',
    'venda_item_id',
    'product_id',
    'codigo',
    'codigo_barras',
    'descricao',
    'localizacao',
    'quantidade_pedida',
    'quantidade_expedida',
    'quantidade_separada',
    'quantidade_conferida',
    'separado',
    'conferido',
])]
class EntregaItem extends Model
{
    protected $table = 'entrega_itens';

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function vendaItem(): BelongsTo
    {
        return $this->belongsTo(VendaItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantidade_pedida' => 'decimal:3',
            'quantidade_expedida' => 'decimal:3',
            'quantidade_separada' => 'decimal:3',
            'quantidade_conferida' => 'decimal:3',
            'separado' => 'boolean',
            'conferido' => 'boolean',
        ];
    }
}
