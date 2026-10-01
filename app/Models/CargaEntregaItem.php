<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'carga_entrega_id',
    'produto_id',
    'codigo',
    'descricao',
    'quantidade_original',
    'quantidade',
    'unidade',
])]
class CargaEntregaItem extends Model
{
    protected $table = 'carga_entrega_itens';

    protected function casts(): array
    {
        return [
            'quantidade_original' => 'float',
            'quantidade' => 'float',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(CargaEntrega::class, 'carga_entrega_id');
    }
}
