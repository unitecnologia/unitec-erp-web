<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhEscalaLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_escala_logs';

    protected $fillable = [
        'escala_id',
        'vinculo_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(RhEscala::class, 'escala_id');
    }

    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(RhEscalaVinculo::class, 'vinculo_id');
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
