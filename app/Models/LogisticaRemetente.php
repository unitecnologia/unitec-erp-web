<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticaRemetente extends Model
{
    protected $table = 'logistica_remetentes';

    protected $fillable = [
        'codigo',
        'nome',
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

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
