<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo_legado',
    'conta_receber_id',
    'data',
    'valor_parcela',
    'perc_juros',
    'juros',
    'multa',
    'perc_desconto',
    'desconto',
    'valor_recebido',
    'plano_conta_id',
    'caixa_conta_id',
    'forma_pagamento_id',
    'numero_cheque',
    'cliente_id',
])]
class ContaReceberPagamento extends Model
{
    protected $table = 'conta_receber_pagamentos';

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class, 'conta_receber_id');
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_id');
    }

    public function caixaConta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    protected function casts(): array
    {
        return [
            'codigo_legado' => 'integer',
            'data' => 'date',
            'valor_parcela' => 'decimal:2',
            'perc_juros' => 'decimal:4',
            'juros' => 'decimal:2',
            'multa' => 'decimal:2',
            'perc_desconto' => 'decimal:4',
            'desconto' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
        ];
    }
}
