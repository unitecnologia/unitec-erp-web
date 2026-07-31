<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeliHubPair extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    protected $table = 'meli_hub_pairs';

    protected $fillable = [
        'uuid',
        'status',
        'empresa_id',
        'client_label',
        'access_token',
        'refresh_token',
        'meli_user_id',
        'nickname',
        'token_expires_at',
        'erro',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
