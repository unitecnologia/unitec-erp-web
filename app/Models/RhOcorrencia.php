<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhOcorrencia extends Model
{
    public const TIPO_ADVERTENCIA = 'advertencia';

    public const TIPO_SUSPENSAO = 'suspensao';

    public const TIPO_ELOGIO = 'elogio';

    public const TIPO_JUSTIFICATIVA = 'justificativa';

    public const TIPO_ATRASO = 'atraso';

    public const TIPO_AUSENCIA = 'ausencia';

    public const TIPO_OUTROS = 'outros';

    public const SIT_REGISTRADA = 'registrada';

    public const SIT_CANCELADA = 'cancelada';

    public const SIT_CORRIGIDA = 'corrigida';

    protected $table = 'rh_ocorrencias';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'tipo',
        'ocorrido_em',
        'descricao',
        'responsavel',
        'situacao',
        'acesso_restrito',
        'user_id',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_ADVERTENCIA => 'Advertência',
            self::TIPO_SUSPENSAO => 'Suspensão',
            self::TIPO_ELOGIO => 'Elogio',
            self::TIPO_JUSTIFICATIVA => 'Justificativa',
            self::TIPO_ATRASO => 'Atraso',
            self::TIPO_AUSENCIA => 'Ausência',
            self::TIPO_OUTROS => 'Outros',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situacoes(): array
    {
        return [
            self::SIT_REGISTRADA => 'Registrada',
            self::SIT_CORRIGIDA => 'Corrigida',
            self::SIT_CANCELADA => 'Cancelada',
        ];
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RhOcorrenciaLog::class, 'ocorrencia_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? (string) $this->tipo;
    }

    public function situacaoLabel(): string
    {
        return self::situacoes()[$this->situacao] ?? (string) $this->situacao;
    }

    public function isCancelada(): bool
    {
        return $this->situacao === self::SIT_CANCELADA;
    }

    public function descricaoResumo(int $limite = 60, bool $podeVerRestrito = true): string
    {
        if ($this->acesso_restrito && ! $podeVerRestrito) {
            return '▪ Conteúdo restrito';
        }

        $texto = trim((string) $this->descricao);
        if ($texto === '') {
            return '—';
        }

        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $limite - 1)).'…';
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->where('situacao', '!=', self::SIT_CANCELADA);
    }

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'date',
            'acesso_restrito' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
