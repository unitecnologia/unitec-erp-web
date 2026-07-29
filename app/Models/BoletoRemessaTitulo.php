<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'boleto_remessa_id', 'empresa_id', 'id_legado', 'emissao', 'vencimento', 'valor',
    'cliente_razao', 'cliente_documento', 'cliente_endereco', 'cliente_numero', 'cliente_bairro',
    'cliente_cidade', 'cliente_uf', 'cliente_cep', 'data_pagamento',
    'cancelamento_loja', 'pagamento_loja', 'alteracao_loja', 'numero_boleto',
])]
class BoletoRemessaTitulo extends Model
{
    protected $table = 'boleto_remessa_titulos';

    public function remessa(): BelongsTo
    {
        return $this->belongsTo(BoletoRemessa::class, 'boleto_remessa_id');
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'datetime',
            'vencimento' => 'datetime',
            'data_pagamento' => 'date',
            'valor' => 'decimal:2',
            'cancelamento_loja' => 'boolean',
            'pagamento_loja' => 'boolean',
            'alteracao_loja' => 'boolean',
        ];
    }
}
