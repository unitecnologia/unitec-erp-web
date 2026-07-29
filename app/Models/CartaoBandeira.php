<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'codigo',
    'nome',
    'ordem',
    'ativo',
])]
class CartaoBandeira extends Model
{
    protected $table = 'cartao_bandeiras';

    public static function nextCodigo(): int
    {
        return (int) (static::query()->max('codigo') ?? 0) + 1;
    }

    /**
     * @return array<string, string> nome => nome
     */
    public static function optionsAtivas(): array
    {
        return static::query()
            ->ativas()
            ->orderByPopularidade()
            ->pluck('nome', 'nome')
            ->all();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByPopularidade(Builder $query): Builder
    {
        return $query->orderBy('ordem')->orderBy('nome');
    }

    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
