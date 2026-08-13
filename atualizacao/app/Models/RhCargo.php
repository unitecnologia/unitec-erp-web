<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhCargo extends Model
{
    protected $table = 'rh_cargos';

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

    public function funcionarios(): HasMany
    {
        return $this->hasMany(RhFuncionario::class, 'cargo_id');
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
