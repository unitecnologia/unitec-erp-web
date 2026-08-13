<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'person_id',
    'codigo_fornecedor',
])]
class ProdutoFornecedor extends Model
{
    protected $table = 'produto_fornecedores';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
