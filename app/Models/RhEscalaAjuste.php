<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhEscalaAjuste extends Model
{
    public const TIPO_TRABALHO = 'trabalho';

    public const TIPO_FOLGA = 'folga';

    public const TIPO_PLANTAO = 'plantao';

    protected $table = 'rh_escala_ajustes';

    protected $fillable = [
        'vinculo_id',
        'funcionario_id',
        'data_ref',
        'tipo',
        'hora_inicio',
        'hora_fim',
        'motivo',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_TRABALHO => 'Trabalho',
            self::TIPO_FOLGA => 'Folga',
            self::TIPO_PLANTAO => 'Plantão',
        ];
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? mb_strtoupper((string) $this->tipo, 'UTF-8');
    }

    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(RhEscalaVinculo::class, 'vinculo_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    protected function casts(): array
    {
        return [
            'data_ref' => 'date',
        ];
    }
}
