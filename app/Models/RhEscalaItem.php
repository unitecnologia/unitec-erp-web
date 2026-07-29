<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhEscalaItem extends Model
{
    public const TIPO_TRABALHO = 'trabalho';

    public const TIPO_FOLGA = 'folga';

    public const TIPO_PLANTAO = 'plantao';

    protected $table = 'rh_escala_itens';

    protected $fillable = [
        'escala_id',
        'funcionario_id',
        'dia_semana',
        'tipo',
        'hora_inicio',
        'hora_fim',
    ];

    public function escala(): BelongsTo
    {
        return $this->belongsTo(RhEscala::class, 'escala_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }
}
