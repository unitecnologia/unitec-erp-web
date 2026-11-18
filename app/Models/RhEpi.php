<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhEpi extends Model
{
    protected $table = 'rh_epis';

    protected $fillable = [
        'codigo',
        'descricao',
        'ca_numero',
        'ca_valido_ate',
        'fabricante',
        'validade_equipamento',
        'intervalo_troca_dias',
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
        return $this->hasMany(RhEpiEntrega::class, 'epi_id');
    }

    public function labelSelect(): string
    {
        $parts = [trim((string) $this->codigo.' — '.$this->descricao)];

        if (filled($this->ca_numero)) {
            $parts[] = 'CA '.$this->ca_numero;
        }

        return implode(' | ', $parts);
    }

    public function caVencido(): bool
    {
        return $this->ca_valido_ate !== null && $this->ca_valido_ate->lt(now()->startOfDay());
    }

    protected function casts(): array
    {
        return [
            'ca_valido_ate' => 'date',
            'validade_equipamento' => 'date',
            'intervalo_troca_dias' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
