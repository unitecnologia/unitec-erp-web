<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhExame extends Model
{
    public const TIPO_ADMISSIONAL = 'admissional';

    public const TIPO_PERIODICO = 'periodico';

    public const TIPO_RETORNO = 'retorno_trabalho';

    public const TIPO_MUDANCA_RISCO = 'mudanca_risco';

    public const TIPO_DEMISSIONAL = 'demissional';

    public const TIPO_OUTRO = 'outro';

    public const CONCL_APTO = 'apto';

    public const CONCL_APTO_RESTRICAO = 'apto_restricao';

    public const CONCL_INAPTO = 'inapto';

    public const CONCL_NAO_INFORMADA = 'nao_informada';

    public const SIT_OK = 'ok';

    public const SIT_PROXIMO = 'proximo';

    public const SIT_PENDENTE = 'pendente';

    public const SIT_SEM = 'sem_prazo';

    protected $table = 'rh_exames';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'tipo',
        'realizado_em',
        'proximo_em',
        'medico_nome',
        'medico_crm',
        'conclusao',
        'observacao',
        'user_id',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    public static function tipos(): array
    {
        return [
            self::TIPO_ADMISSIONAL => 'Admissional',
            self::TIPO_PERIODICO => 'Periódico',
            self::TIPO_RETORNO => 'Retorno ao trabalho',
            self::TIPO_MUDANCA_RISCO => 'Mudança de risco',
            self::TIPO_DEMISSIONAL => 'Demissional',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function conclusoes(): array
    {
        return [
            self::CONCL_APTO => 'Apto',
            self::CONCL_APTO_RESTRICAO => 'Apto c/ restrição',
            self::CONCL_INAPTO => 'Inapto',
            self::CONCL_NAO_INFORMADA => 'Não informada',
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
        return $this->hasMany(RhExameLog::class, 'exame_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function tipoLabel(): string
    {
        return self::tipos()[$this->tipo] ?? (string) $this->tipo;
    }

    public function conclusaoLabel(): string
    {
        return self::conclusoes()[$this->conclusao] ?? (string) $this->conclusao;
    }

    public function situacaoPrazo(int $diasAlerta = 30): string
    {
        if (! $this->proximo_em) {
            return self::SIT_SEM;
        }

        $hoje = Carbon::today();
        if ($this->proximo_em->lt($hoje)) {
            return self::SIT_PENDENTE;
        }

        if ($this->proximo_em->lte($hoje->copy()->addDays(max(1, $diasAlerta)))) {
            return self::SIT_PROXIMO;
        }

        return self::SIT_OK;
    }

    public function situacaoPrazoLabel(int $diasAlerta = 30): string
    {
        return match ($this->situacaoPrazo($diasAlerta)) {
            self::SIT_PENDENTE => 'Pendente',
            self::SIT_PROXIMO => 'Próximo',
            self::SIT_OK => 'OK',
            default => 'Sem prazo',
        };
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    protected function casts(): array
    {
        return [
            'realizado_em' => 'date',
            'proximo_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
