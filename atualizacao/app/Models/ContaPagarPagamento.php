<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo_legado',
    'conta_pagar_id',
    'data',
    'valor_parcela',
    'perc_juros',
    'juros',
    'perc_desconto',
    'desconto',
    'valor_pago',
    'plano_conta_id',
    'caixa_conta_id',
    'forma_pagamento_id',
    'numero_cheque',
    'fornecedor_id',
    'lote_legado',
])]
class ContaPagarPagamento extends Model
{
    protected $table = 'conta_pagar_pagamentos';

    public function contaPagar(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
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

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'fornecedor_id');
    }

    protected function casts(): array
    {
        return [
            'codigo_legado' => 'integer',
            'data' => 'date',
            'valor_parcela' => 'decimal:2',
            'perc_juros' => 'decimal:4',
            'juros' => 'decimal:2',
            'perc_desconto' => 'decimal:4',
            'desconto' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'lote_legado' => 'integer',
        ];
    }
}
