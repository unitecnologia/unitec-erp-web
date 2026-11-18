<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhFeriasPeriodo extends Model
{
    public const SIT_ABERTO = 'aberto';

    public const SIT_PARCIAL = 'parcial';

    public const SIT_GOZADO = 'gozado';

    public const SIT_VENCIDO = 'vencido';

    public const SIT_CANCELADO = 'cancelado';

    protected $table = 'rh_ferias_periodos';

    protected $fillable = [
        'codigo',
        'funcionario_id',
        'aquisitivo_inicio',
        'aquisitivo_fim',
        'concessivo_inicio',
        'concessivo_fim',
        'dias_direito',
        'dias_saldo',
        'situacao',
        'observacao',
        'user_id',
        'ativo',
    ];

    /**
     * @return array<string, string>
     */
    public static function situacoes(): array
    {
        return [
            self::SIT_ABERTO => 'Aberto',
            self::SIT_PARCIAL => 'Parcial',
            self::SIT_GOZADO => 'Gozado',
            self::SIT_VENCIDO => 'Vencido',
            self::SIT_CANCELADO => 'Cancelado',
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

    public function gozos(): HasMany
    {
        return $this->hasMany(RhFeriasGozo::class, 'periodo_id');
    }

    public function situacaoLabel(): string
    {
        return self::situacoes()[$this->situacao] ?? (string) $this->situacao;
    }

    public function aquisitivoResumo(): string
    {
        $ini = $this->aquisitivo_inicio?->format('d/m/Y') ?? '—';
        $fim = $this->aquisitivo_fim?->format('d/m/Y') ?? '—';

        return $ini.' — '.$fim;
    }

    public function concessivoApertando(int $dias = 60): bool
    {
        if (! $this->concessivo_fim || (int) $this->dias_saldo <= 0) {
            return false;
        }

        if (in_array($this->situacao, [self::SIT_GOZADO, self::SIT_CANCELADO], true)) {
            return false;
        }

        $hoje = Carbon::today();

        return $this->concessivo_fim->gte($hoje)
            && $this->concessivo_fim->lte($hoje->copy()->addDays(max(1, $dias)));
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    protected function casts(): array
    {
        return [
            'aquisitivo_inicio' => 'date',
            'aquisitivo_fim' => 'date',
            'concessivo_inicio' => 'date',
            'concessivo_fim' => 'date',
            'dias_direito' => 'integer',
            'dias_saldo' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
