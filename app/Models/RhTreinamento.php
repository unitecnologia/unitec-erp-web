<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhTreinamento extends Model
{
    protected $table = 'rh_treinamentos';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'tipo',
        'carga_horaria',
        'instrutor',
        'periodicidade_dias',
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

    public function realizacoes(): HasMany
    {
        return $this->hasMany(RhTreinamentoRealizacao::class, 'treinamento_id');
    }

    public function labelSelect(): string
    {
        $parts = [trim((string) $this->codigo.' — '.$this->nome)];

        if (filled($this->tipo)) {
            $parts[] = (string) $this->tipo;
        }

        if ($this->carga_horaria !== null) {
            $parts[] = number_format((float) $this->carga_horaria, 2, ',', '.').'h';
        }

        return implode(' | ', $parts);
    }

    protected function casts(): array
    {
        return [
            'carga_horaria' => 'decimal:2',
            'periodicidade_dias' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
