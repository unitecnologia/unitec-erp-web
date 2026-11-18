<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lote_id',
    'tef_intencao_id',
    'pdv_venda_id',
    'valor_bruto',
    'valor_taxa',
    'valor_liquido',
    'nsu',
    'autorizacao',
    'bandeira',
    'transaction_type',
    'installment_count',
    'parcela_numero',
    'acquirer_cnpj',
    'pago_em',
    'data_prevista_parcela',
])]
class TefLiquidacaoLoteItem extends Model
{
    protected $table = 'tef_liquidacao_lote_itens';

    protected function casts(): array
    {
        return [
            'valor_bruto' => 'decimal:2',
            'valor_taxa' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'pago_em' => 'datetime',
            'data_prevista_parcela' => 'date',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TefLiquidacaoLote::class, 'lote_id');
    }

    public function intencao(): BelongsTo
    {
        return $this->belongsTo(TefIntencao::class, 'tef_intencao_id');
    }

    public function pdvVenda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }
}
