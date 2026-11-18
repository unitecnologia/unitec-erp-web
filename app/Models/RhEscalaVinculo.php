<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhEscalaVinculo extends Model
{
    protected $table = 'rh_escala_vinculos';

    protected $fillable = [
        'codigo',
        'escala_id',
        'funcionario_id',
        'inicio_em',
        'termino_em',
        'observacao',
        'user_id',
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

    public function escala(): BelongsTo
    {
        return $this->belongsTo(RhEscala::class, 'escala_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(RhEscalaAjuste::class, 'vinculo_id');
    }

    public function isVigenteOn(string $dataRef): bool
    {
        if (! $this->ativo) {
            return false;
        }

        $ref = $dataRef;
        $inicio = $this->inicio_em?->toDateString();
        if ($inicio !== null && $ref < $inicio) {
            return false;
        }

        $termino = $this->termino_em?->toDateString();
        if ($termino !== null && $ref > $termino) {
            return false;
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'inicio_em' => 'date',
            'termino_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
