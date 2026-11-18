<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhHoleriteLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_holerite_logs';

    protected $fillable = [
        'holerite_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function holerite(): BelongsTo
    {
        return $this->belongsTo(RhHolerite::class, 'holerite_id');
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
