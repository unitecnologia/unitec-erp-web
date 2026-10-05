<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioEtapa extends Model
{
    public const STATUS_ABERTA = 'aberta';

    public const STATUS_FECHADA = 'fechada';

    protected $table = 'inventario_etapas';

    protected $fillable = [
        'inventario_contagem_id',
        'nome',
        'status',
        'user_id',
        'fechada_em',
        'fechada_por',
    ];

    protected function casts(): array
    {
        return [
            'fechada_em' => 'datetime',
        ];
    }

    public function balanco(): BelongsTo
    {
        return $this->belongsTo(InventarioContagem::class, 'inventario_contagem_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioContagemItem::class, 'inventario_etapa_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
