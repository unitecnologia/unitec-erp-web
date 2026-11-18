<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhFeriasGozoLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_ferias_gozo_logs';

    protected $fillable = [
        'gozo_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function gozo(): BelongsTo
    {
        return $this->belongsTo(RhFeriasGozo::class, 'gozo_id');
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
