<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvCaixaMovimento extends Model
{
    protected $table = 'pdv_caixa_movimentos';

    protected $fillable = [
        'pdv_caixa_sessao_id',
        'tipo',
        'historico',
        'forma_pagamento',
        'plano_conta_codigo',
        'sangria_destino',
        'entrada',
        'saida',
        'pdv_venda_id',
    ];

    protected function casts(): array
    {
        return [
            'entrada' => 'decimal:2',
            'saida' => 'decimal:2',
        ];
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(PdvCaixaSessao::class, 'pdv_caixa_sessao_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }
}
