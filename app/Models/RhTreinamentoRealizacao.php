<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhTreinamentoRealizacao extends Model
{
    protected $table = 'rh_treinamento_realizacoes';

    protected $fillable = [
        'codigo',
        'treinamento_id',
        'realizado_em',
        'vencimento_em',
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

    public function treinamento(): BelongsTo
    {
        return $this->belongsTo(RhTreinamento::class, 'treinamento_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(RhTreinamentoParticipante::class, 'realizacao_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RhTreinamentoRealizacaoLog::class, 'realizacao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'realizado_em' => 'date',
            'vencimento_em' => 'date',
            'ativo' => 'boolean',
        ];
    }
}
