<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhUniformeEntrega extends Model
{
    public const SIT_ENTREGUE = 'entregue';

    public const SIT_DEVOLVIDO = 'devolvido';

    public const SIT_TROCADO = 'trocado';

    public const SIT_SUBSTITUIDO = 'substituido';

    public const SIT_CANCELADO = 'cancelado';

    protected $table = 'rh_uniforme_entregas';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'uniforme_id',
        'quantidade',
        'entregue_em',
        'situacao',
        'observacao',
        'motivo_movimentacao',
        'devolvido_em',
        'substitui_id',
        'substituido_por_id',
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

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function uniforme(): BelongsTo
    {
        return $this->belongsTo(RhUniforme::class, 'uniforme_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function substitui(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitui_id');
    }

    public function substituidoPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituido_por_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RhUniformeEntregaLog::class, 'entrega_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function situacaoLabel(): string
    {
        return match ($this->situacao) {
            self::SIT_ENTREGUE => 'Entregue',
            self::SIT_DEVOLVIDO => 'Devolvido',
            self::SIT_TROCADO => 'Trocado',
            self::SIT_SUBSTITUIDO => 'Substituído',
            self::SIT_CANCELADO => 'Cancelado',
            default => (string) $this->situacao,
        };
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->where('situacao', self::SIT_ENTREGUE);
    }

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'entregue_em' => 'date',
            'devolvido_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
