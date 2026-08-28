<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocao extends Model
{
    protected $table = 'promocoes';

    protected $fillable = [
        'empresa_id',
        'descricao',
        'data_inicio',
        'data_fim',
        'ativa',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'ativa' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PromocaoItem::class, 'promocao_id');
    }
}
