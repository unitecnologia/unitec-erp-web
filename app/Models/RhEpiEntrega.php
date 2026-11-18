<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhEpiEntrega extends Model
{
    public const SIT_ENTREGUE = 'entregue';

    public const SIT_DEVOLVIDO = 'devolvido';

    public const SIT_SUBSTITUIDO = 'substituido';

    public const SIT_CANCELADO = 'cancelado';

    protected $table = 'rh_epi_entregas';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'epi_id',
        'quantidade',
        'entregue_em',
        'previsao_troca',
        'situacao',
        'observacao',
        'motivo_substituicao',
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

    public function epi(): BelongsTo
    {
        return $this->belongsTo(RhEpi::class, 'epi_id');
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
        return $this->hasMany(RhEpiEntregaLog::class, 'entrega_id');
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
            self::SIT_SUBSTITUIDO => 'Substituído',
            self::SIT_CANCELADO => 'Cancelado',
            default => (string) $this->situacao,
        };
    }

    public function trocaSituacao(int $diasAlerta = 30): string
    {
        if ($this->situacao !== self::SIT_ENTREGUE || ! $this->previsao_troca) {
            return 'sem';
        }

        $hoje = Carbon::today();
        if ($this->previsao_troca->lt($hoje)) {
            return 'vencida';
        }

        if ($this->previsao_troca->lte($hoje->copy()->addDays(max(1, $diasAlerta)))) {
            return 'proxima';
        }

        return 'ok';
    }

    public function trocaSituacaoLabel(int $diasAlerta = 30): string
    {
        return match ($this->trocaSituacao($diasAlerta)) {
            'vencida' => 'Troca vencida',
            'proxima' => 'Troca próxima',
            'ok' => 'OK',
            default => '—',
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
            'previsao_troca' => 'date',
            'devolvido_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
