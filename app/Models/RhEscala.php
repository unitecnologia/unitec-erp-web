<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhEscala extends Model
{
    public const TIPO_5X2 = '5x2';

    public const TIPO_6X1 = '6x1';

    public const TIPO_12X36 = '12x36';

    public const TIPO_PERSONALIZADA = 'personalizada';

    protected $table = 'rh_escalas';

    protected $fillable = [
        'codigo',
        'nome',
        'tipo',
        'hora_entrada',
        'hora_saida',
        'intervalo_minutos',
        'dias_trabalho',
        'dias_folga',
        'vigencia_inicio',
        'vigencia_fim',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_5X2 => '5×2',
            self::TIPO_6X1 => '6×1',
            self::TIPO_12X36 => '12×36',
            self::TIPO_PERSONALIZADA => 'Personalizada',
        ];
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? (string) $this->tipo;
    }

    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RhEscalaItem::class, 'escala_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(RhEscalaVinculo::class, 'escala_id');
    }

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativo' => 'boolean',
            'dias_trabalho' => 'array',
            'dias_folga' => 'array',
            'intervalo_minutos' => 'integer',
        ];
    }
}
