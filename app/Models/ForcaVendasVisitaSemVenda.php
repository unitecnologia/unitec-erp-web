<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'device_uuid',
    'user_id',
    'empresa_id',
    'cliente_id',
    'vendedor_id',
    'motivo',
    'latitude',
    'longitude',
    'status',
    'erro',
    'client_created_at',
    'received_at',
])]
class ForcaVendasVisitaSemVenda extends Model
{
    public const STATUS_IMPORTADO = 'importado';

    public const STATUS_ERRO = 'erro';

    protected $table = 'forca_vendas_visitas_sem_venda';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'client_created_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
