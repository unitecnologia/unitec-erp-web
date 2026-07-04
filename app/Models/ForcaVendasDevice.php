<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_uuid',
    'user_id',
    'empresa_id',
    'device_name',
    'platform',
    'app_version',
    'status',
    'pairing_code',
    'current_token_id',
    'last_seen_at',
    'last_pull_at',
    'registered_at',
    'approved_at',
    'approved_by',
    'revoked_at',
])]
class ForcaVendasDevice extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REVOGADO = 'revogado';

    protected $table = 'forca_vendas_devices';

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_pull_at' => 'datetime',
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->revoked_at === null && $this->status === self::STATUS_APROVADO;
    }

    public function isPending(): bool
    {
        return $this->revoked_at === null && $this->status !== self::STATUS_APROVADO;
    }

    public function situacaoLabel(): string
    {
        if ($this->revoked_at !== null) {
            return 'Revogado';
        }

        return $this->status === self::STATUS_APROVADO ? 'Ativo' : 'Pendente';
    }
}
