<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'descricao',
    'ativo',
])]
class PriceTable extends Model
{
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductPriceTableItem::class);
    }
}
