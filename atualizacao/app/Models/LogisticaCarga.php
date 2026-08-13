<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero',
    'data',
    'entregador_id',
    'motorista_nome',
    'status',
    'observacoes',
    'saiu_em',
    'finalizada_em',
])]
class LogisticaCarga extends Model
{
    public const STATUS_MONTANDO = 'montando';

    public const STATUS_SAIU = 'saiu';

    public const STATUS_FINALIZADA = 'finalizada';

    public const STATUS_CANCELADA = 'cancelada';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_MONTANDO => 'Montando',
            self::STATUS_SAIU => 'Saiu',
            self::STATUS_FINALIZADA => 'Finalizada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(Entregador::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'logistica_carga_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'saiu_em' => 'datetime',
            'finalizada_em' => 'datetime',
        ];
    }
}
