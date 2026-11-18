<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhBeneficioVinculo extends Model
{
    public const SIT_ATIVO = 'ativo';

    public const SIT_CANCELADO = 'cancelado';

    public const SIT_ENCERRADO = 'encerrado';

    protected $table = 'rh_beneficio_vinculos';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'beneficio_id',
        'inicio_em',
        'termino_em',
        'valor',
        'participacao_funcionario',
        'situacao',
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

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(RhBeneficio::class, 'beneficio_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RhBeneficioVinculoLog::class, 'vinculo_id');
    }

    public function situacaoLabel(): string
    {
        return match ($this->situacao) {
            self::SIT_ATIVO => 'Ativo',
            self::SIT_CANCELADO => 'Cancelado',
            self::SIT_ENCERRADO => 'Encerrado',
            default => (string) $this->situacao,
        };
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('situacao', self::SIT_ATIVO)->where('ativo', true);
    }

    protected function casts(): array
    {
        return [
            'inicio_em' => 'date',
            'termino_em' => 'date',
            'valor' => 'decimal:2',
            'participacao_funcionario' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }
}
