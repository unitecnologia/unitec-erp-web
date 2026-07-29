<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'boleto_retorno_id', 'empresa_id', 'id_legado', 'titulo_legado',
    'titulo_localizado', 'titulo_ja_liquidado', 'titulo_sem_registro', 'titulo_liquidado_limite', 'titulo_recusado',
    'seu_numero', 'nosso_numero', 'valor_documento', 'valor_pago', 'valor_recebido',
    'valor_juros', 'valor_desconto', 'valor_despesa', 'data_ocorrencia',
    'banco_id', 'agencia_id', 'origem', 'forma_pagamento', 'tipo_ocorrencia',
    'tipo_ocorrencia_desc', 'mot_rej_comando', 'mot_rej_comando_desc', 'historico',
])]
class BoletoRetornoTitulo extends Model
{
    protected $table = 'boleto_retorno_titulos';

    public function retorno(): BelongsTo
    {
        return $this->belongsTo(BoletoRetorno::class, 'boleto_retorno_id');
    }

    protected function casts(): array
    {
        return [
            'titulo_localizado' => 'boolean',
            'titulo_ja_liquidado' => 'boolean',
            'titulo_sem_registro' => 'boolean',
            'titulo_liquidado_limite' => 'boolean',
            'titulo_recusado' => 'boolean',
            'valor_documento' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
            'valor_juros' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_despesa' => 'decimal:2',
            'data_ocorrencia' => 'date',
        ];
    }
}
