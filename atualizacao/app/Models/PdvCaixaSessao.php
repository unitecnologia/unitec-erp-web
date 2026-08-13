<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdvCaixaSessao extends Model
{
    protected $table = 'pdv_caixa_sessoes';

    protected $fillable = [
        'user_id',
        'empresa_id',
        'terminal_id',
        'valor_abertura',
        'valor_fechamento',
        'aberto_em',
        'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_abertura' => 'decimal:2',
            'valor_fechamento' => 'decimal:2',
            'aberto_em' => 'datetime',
            'fechado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(PdvCaixaMovimento::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(PdvVenda::class);
    }

    public function isAberto(): bool
    {
        return $this->fechado_em === null;
    }

    public function saldoEntradas(): float
    {
        return (float) $this->movimentos()->sum('entrada');
    }

    public function saldoSaidas(): float
    {
        return (float) $this->movimentos()->sum('saida');
    }

    public function saldoTotal(): float
    {
        return round($this->saldoEntradas() - $this->saldoSaidas(), 2);
    }

    public function saldoDinheiro(): float
    {
        return round(
            (float) $this->movimentos()
                ->where(function ($query): void {
                    $query->where('forma_pagamento', 'DINHEIRO')
                        ->orWhere('tipo', 'abertura')
                        ->orWhere('tipo', 'suprimento');
                })
                ->sum('entrada')
            - (float) $this->movimentos()
                ->where('forma_pagamento', 'DINHEIRO')
                ->sum('saida'),
            2
        );
    }
}
