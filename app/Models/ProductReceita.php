<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Receita de fabricação do produto acabado.
 *
 * Alcance: global (1 receita por product_id). Produtos são compartilhados entre empresas;
 * o isolamento de estoque ocorre na fabricação (empresa/depósito logados), não neste cadastro.
 */
#[Fillable([
    'product_id',
    'qtd_produzida',
    'ativo',
])]
class ProductReceita extends Model
{
    protected $table = 'product_receitas';

    protected function casts(): array
    {
        return [
            'qtd_produzida' => 'decimal:3',
            'ativo' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ProductReceitaItem::class, 'receita_id');
    }
}
