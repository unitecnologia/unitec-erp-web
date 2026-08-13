<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'dia_semana',
    'ordem',
])]
class PersonVisitaDia extends Model
{
    protected $table = 'person_visita_dias';

    public const SEGUNDA = 1;

    public const TERCA = 2;

    public const QUARTA = 3;

    public const QUINTA = 4;

    public const SEXTA = 5;

    public const SABADO = 6;

    public const DOMINGO = 7;

    /**
     * @return array<int, string>
     */
    public static function diasLabels(): array
    {
        return [
            self::SEGUNDA => 'Segunda',
            self::TERCA => 'Terça',
            self::QUARTA => 'Quarta',
            self::QUINTA => 'Quinta',
            self::SEXTA => 'Sexta',
            self::SABADO => 'Sábado',
            self::DOMINGO => 'Domingo',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function diasAbrev(): array
    {
        return [
            self::SEGUNDA => 'Seg',
            self::TERCA => 'Ter',
            self::QUARTA => 'Qua',
            self::QUINTA => 'Qui',
            self::SEXTA => 'Sex',
            self::SABADO => 'Sáb',
            self::DOMINGO => 'Dom',
        ];
    }

    protected function casts(): array
    {
        return [
            'person_id' => 'integer',
            'dia_semana' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function nextOrdem(int $diaSemana, ?int $vendedorId = null): int
    {
        $query = static::query()->where('dia_semana', $diaSemana);

        if ($vendedorId) {
            $query->whereHas('person', function ($q) use ($vendedorId): void {
                $q->where('vendedor_fv_id', $vendedorId);
            });
        }

        return ((int) $query->max('ordem')) + 1;
    }
}
