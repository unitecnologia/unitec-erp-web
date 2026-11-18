<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'caixa_conta_id',
    'extrato_lancamento_id',
    'caixa_lancamento_id',
    'status',
    'identificacao',
    'tipo_extrato',
    'valor_extrato',
    'valor_caixa',
    'data_movimento_extrato',
    'data_emissao_caixa',
    'historico_extrato',
    'fitid',
    'documento_caixa',
    'historico_caixa',
    'conciliado_por_user_id',
    'conciliado_em',
    'revertido_por_user_id',
    'revertido_em',
    'motivo_reversao',
    'observacao',
    'lock_extrato',
    'lock_caixa',
])]
class ExtratoConciliacao extends Model
{
    public const STATUS_ATIVA = 'ativa';

    public const STATUS_REVERTIDA = 'revertida';

    public const IDENTIFICACAO_EXATA = 'exata';

    public const IDENTIFICACAO_FRACA = 'fraca';

    public const IDENTIFICACAO_COMPATIVEL = 'compativel';

    protected $table = 'extrato_conciliacoes';

    protected function casts(): array
    {
        return [
            'valor_extrato' => 'decimal:2',
            'valor_caixa' => 'decimal:2',
            'data_movimento_extrato' => 'date',
            'data_emissao_caixa' => 'date',
            'conciliado_em' => 'datetime',
            'revertido_em' => 'datetime',
        ];
    }

    public function extratoLancamento(): BelongsTo
    {
        return $this->belongsTo(ExtratoLancamento::class, 'extrato_lancamento_id');
    }

    public function caixaLancamento(): BelongsTo
    {
        return $this->belongsTo(CaixaLancamento::class, 'caixa_lancamento_id');
    }

    public function conciliadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliado_por_user_id');
    }

    public function revertidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertido_por_user_id');
    }

    public function isAtiva(): bool
    {
        return $this->status === self::STATUS_ATIVA;
    }
}
