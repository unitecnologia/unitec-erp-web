<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'data',
    'product_id',
    'qtd_ajust',
])]
class AjusteEstoque extends Model
{
    protected $table = 'ajustes_estoque';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventarioContagem(): HasOne
    {
        return $this->hasOne(InventarioContagemItem::class, 'ajuste_estoque_id');
    }

    public function scopeComOrigem(Builder $query): Builder
    {
        return $query->withExists('inventarioContagem');
    }

    public function origemLabel(): string
    {
        if (array_key_exists('inventario_contagem_exists', $this->attributes)) {
            return $this->inventario_contagem_exists ? 'App' : 'ERP';
        }

        return $this->inventarioContagem()->exists() ? 'App' : 'ERP';
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'qtd_ajust' => 'decimal:3',
        ];
    }
}
