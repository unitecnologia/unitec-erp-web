<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhOcorrenciaLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_ocorrencia_logs';

    protected $fillable = [
        'ocorrencia_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(RhOcorrencia::class, 'ocorrencia_id');
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
