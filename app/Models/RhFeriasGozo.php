<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RhFeriasGozo extends Model
{
    public const SIT_PROGRAMADA = 'programada';

    public const SIT_EM_ANDAMENTO = 'em_andamento';

    public const SIT_CONCLUIDA = 'concluida';

    public const SIT_CANCELADA = 'cancelada';

    protected $table = 'rh_ferias_gozos';

    protected $fillable = [
        'codigo',
        'periodo_id',
        'funcionario_id',
        'gozo_inicio',
        'gozo_fim',
        'dias',
        'fracao',
        'aviso_em',
        'situacao',
        'ajuste_admin',
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
            self::SIT_PROGRAMADA => 'Programada',
            self::SIT_EM_ANDAMENTO => 'Em andamento',
            self::SIT_CONCLUIDA => 'Concluída',
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

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(RhFeriasPeriodo::class, 'periodo_id');
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
        return $this->hasMany(RhFeriasGozoLog::class, 'gozo_id');
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function situacaoLabel(): string
    {
        return self::situacoes()[$this->situacao] ?? (string) $this->situacao;
    }

    public function alertaAvisoPrazo(int $diasMinimos = 30): bool
    {
        if ($this->situacao === self::SIT_CANCELADA || ! $this->gozo_inicio) {
            return false;
        }

        if ($this->ajuste_admin) {
            return false;
        }

        if (! $this->aviso_em) {
            return true;
        }

        $limite = $this->gozo_inicio->copy()->subDays(max(1, $diasMinimos));

        return $this->aviso_em->gt($limite);
    }

    public function alertaAvisoLabel(int $diasMinimos = 30): string
    {
        if (! $this->alertaAvisoPrazo($diasMinimos)) {
            return 'OK';
        }

        if (! $this->aviso_em) {
            return 'Sem aviso';
        }

        return 'Aviso tardio';
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function emAndamentoHoje(): bool
    {
        if ($this->situacao !== self::SIT_EM_ANDAMENTO) {
            return false;
        }

        $hoje = Carbon::today();

        return $this->gozo_inicio && $this->gozo_fim
            && $this->gozo_inicio->lte($hoje)
            && $this->gozo_fim->gte($hoje);
    }

    protected function casts(): array
    {
        return [
            'gozo_inicio' => 'date',
            'gozo_fim' => 'date',
            'aviso_em' => 'date',
            'dias' => 'integer',
            'fracao' => 'integer',
            'ajuste_admin' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
