<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhTreinamentoRealizacaoLog extends Model
{
    public $timestamps = false;

    protected $table = 'rh_treinamento_realizacao_logs';

    protected $fillable = [
        'realizacao_id',
        'user_id',
        'acao',
        'antes',
        'depois',
        'motivo',
        'created_at',
    ];

    public function realizacao(): BelongsTo
    {
        return $this->belongsTo(RhTreinamentoRealizacao::class, 'realizacao_id');
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
