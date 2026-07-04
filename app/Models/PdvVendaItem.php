<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaItem extends Model
{
    protected $table = 'pdv_venda_itens';

    protected $fillable = [
        'pdv_venda_id',
        'product_id',
        'product_grade_id',
        'product_serial_id',
        'codigo',
        'descricao',
        'unidade',
        'observacao',
        'quantidade',
        'preco_unitario',
        'desconto',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'desconto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
