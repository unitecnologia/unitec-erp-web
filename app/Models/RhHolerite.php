<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhHolerite extends Model
{
    protected $table = 'rh_holerites';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'competencia_ano',
        'competencia_mes',
        'pago_em',
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

    /**
     * @return array<int, string>
     */
    public static function meses(): array
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RhHoleriteLog::class, 'holerite_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function competenciaLabel(): string
    {
        $mes = max(1, min(12, (int) $this->competencia_mes));

        return sprintf('%02d/%d', $mes, (int) $this->competencia_ano);
    }

    public function mesLabel(): string
    {
        return self::meses()[(int) $this->competencia_mes] ?? (string) $this->competencia_mes;
    }

    public function situacaoLabel(): string
    {
        return $this->ativo ? 'Ativo' : 'Inativo';
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function scopeInativos(Builder $query): Builder
    {
        return $query->where('ativo', false);
    }

    public function scopeCompetencia(Builder $query, ?int $ano, ?int $mes): Builder
    {
        if ($ano !== null && $ano > 0) {
            $query->where('competencia_ano', $ano);
        }

        if ($mes !== null && $mes >= 1 && $mes <= 12) {
            $query->where('competencia_mes', $mes);
        }

        return $query;
    }

    public function scopeHolerites(Builder $query): Builder
    {
        return $query;
    }

    protected function casts(): array
    {
        return [
            'competencia_ano' => 'integer',
            'competencia_mes' => 'integer',
            'pago_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
