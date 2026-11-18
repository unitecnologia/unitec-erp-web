<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricacaoItem extends Model
{
    protected $table = 'fabricacao_itens';

    protected $fillable = [
        'fabricacao_id',
        'component_product_id',
        'produto_codigo',
        'produto_descricao',
        'unidade',
        'quantidade_consumida',
        'custo_unitario',
        'custo_total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_consumida' => 'decimal:3',
            'custo_unitario' => 'decimal:2',
            'custo_total' => 'decimal:2',
        ];
    }

    public function fabricacao(): BelongsTo
    {
        return $this->belongsTo(Fabricacao::class);
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
