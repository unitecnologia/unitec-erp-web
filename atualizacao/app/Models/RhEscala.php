<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhEscala extends Model
{
    protected $table = 'rh_escalas';

    protected $fillable = [
        'codigo',
        'nome',
        'vigencia_inicio',
        'vigencia_fim',
        'ativo',
    ];

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

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
