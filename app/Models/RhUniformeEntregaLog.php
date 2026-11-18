<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhUniformeEntregaLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_uniforme_entrega_logs';

    protected $fillable = [
        'entrega_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(RhUniformeEntrega::class, 'entrega_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'depois' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
