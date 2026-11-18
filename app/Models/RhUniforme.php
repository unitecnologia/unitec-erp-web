<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhUniforme extends Model
{
    protected $table = 'rh_uniformes';

    protected $fillable = [
        'codigo',
        'descricao',
        'tipo',
        'tamanho',
        'cor',
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

    public function entregas(): HasMany
    {
        return $this->hasMany(RhUniformeEntrega::class, 'uniforme_id');
    }

    public function labelSelect(): string
    {
        $parts = [trim((string) $this->codigo.' — '.$this->descricao)];

        if (filled($this->tipo)) {
            $parts[] = (string) $this->tipo;
        }

        if (filled($this->tamanho)) {
            $parts[] = 'Tam. '.$this->tamanho;
        }

        if (filled($this->cor)) {
            $parts[] = (string) $this->cor;
        }

        return implode(' | ', $parts);
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
