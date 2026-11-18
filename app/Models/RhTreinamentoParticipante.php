<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhTreinamentoParticipante extends Model
{
    public const SIT_ATIVO = 'ativo';

    public const SIT_CANCELADO = 'cancelado';

    protected $table = 'rh_treinamento_participantes';

    protected $fillable = [
        'codigo',
        'realizacao_id',
        'funcionario_id',
        'vencimento_em',
        'situacao',
        'observacao',
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

    public function realizacao(): BelongsTo
    {
        return $this->belongsTo(RhTreinamentoRealizacao::class, 'realizacao_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function situacaoLabel(): string
    {
        return match ($this->situacao) {
            self::SIT_ATIVO => 'Ativo',
            self::SIT_CANCELADO => 'Cancelado',
            default => (string) $this->situacao,
        };
    }

    /**
     * @return 'em_dia'|'proximo'|'vencido'|'sem'
     */
    public function validadeSituacao(int $dias = 30): string
    {
        if (! $this->vencimento_em) {
            return 'sem';
        }

        $hoje = Carbon::today();
        if ($this->vencimento_em->lt($hoje)) {
            return 'vencido';
        }

        if ($this->vencimento_em->lte($hoje->copy()->addDays(max(1, $dias)))) {
            return 'proximo';
        }

        return 'em_dia';
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true)->where('situacao', self::SIT_ATIVO);
    }

    protected function casts(): array
    {
        return [
            'vencimento_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
