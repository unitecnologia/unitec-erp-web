<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorização do retaguarda para apagar a base local de um aparelho do
 * Força de Vendas. Vale uma única vez: pendente → concluido.
 */
#[Fillable([
    'uuid',
    'forca_vendas_device_id',
    'device_uuid',
    'status',
    'authorized_by',
    'authorized_at',
    'completed_at',
    'completed_app_version',
])]
class ForcaVendasDeviceReset extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_CONCLUIDO = 'concluido';

    protected $table = 'forca_vendas_device_resets';

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ForcaVendasDevice::class, 'forca_vendas_device_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function isPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }
}
