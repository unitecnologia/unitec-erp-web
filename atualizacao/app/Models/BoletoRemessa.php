<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'id_legado', 'uuid_legado', 'data', 'banco_id', 'agencia', 'agencia_digito',
    'conta', 'conta_digito', 'codigo_cedente', 'convenio', 'modalidade', 'carteira',
    'local_pagamento', 'mensagem', 'instrucao1', 'instrucao2', 'percentual_juros', 'percentual_multa',
    'data_geracao', 'local_arquivo', 'data_proc_banco', 'cancelada', 'qtd_titulos', 'valor_total',
])]
class BoletoRemessa extends Model
{
    protected $table = 'boleto_remessas';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(BoletoRemessaTitulo::class, 'boleto_remessa_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
            'data_geracao' => 'datetime',
            'data_proc_banco' => 'datetime',
            'cancelada' => 'boolean',
            'percentual_juros' => 'decimal:4',
            'percentual_multa' => 'decimal:4',
            'valor_total' => 'decimal:2',
        ];
    }
}
