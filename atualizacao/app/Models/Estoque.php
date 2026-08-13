<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estoque extends Model
{
    protected $table = 'estoques';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nome',
        'vendedor_id',
        'ativo',
    ];

    public static function nextCodigo(int $empresaId): string
    {
        $max = static::query()
            ->where('empresa_id', $empresaId)
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function vendedores(): HasMany
    {
        return $this->hasMany(Vendedor::class, 'estoque_id');
    }

    public function label(): string
    {
        return trim($this->codigo.' — '.$this->nome);
    }
}
