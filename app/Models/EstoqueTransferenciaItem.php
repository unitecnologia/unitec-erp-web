<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstoqueTransferenciaItem extends Model
{
    protected $table = 'estoque_transferencia_itens';

    protected $fillable = [
        'estoque_transferencia_id',
        'item',
        'product_id',
        'produto_codigo',
        'produto_descricao',
        'qtd',
        'qtd_disponivel_origem',
    ];

    protected function casts(): array
    {
        return [
            'qtd' => 'decimal:3',
            'qtd_disponivel_origem' => 'decimal:3',
        ];
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(EstoqueTransferencia::class, 'estoque_transferencia_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
