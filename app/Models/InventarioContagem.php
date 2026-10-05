<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioContagem extends Model
{
    public const STATUS_ABERTO = 'aberto';

    public const STATUS_ENCERRADO = 'encerrado';

    protected $table = 'inventario_contagens';

    protected $fillable = [
        'empresa_id',
        'estoque_id',
        'user_id',
        'data',
        'status',
        'finalizada_em',
        'finalizada_por',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'finalizada_em' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(InventarioContagemItem::class);
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(InventarioEtapa::class, 'inventario_contagem_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function finalizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizada_por');
    }
}
