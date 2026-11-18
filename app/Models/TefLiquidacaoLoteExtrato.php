<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'caixa_conta_id',
    'lote_id',
    'extrato_lancamento_id',
    'valor_vinculado',
    'valor_lote_liquido_ref',
    'valor_extrato_ref',
    'diferenca',
    'diferenca_motivo',
    'status',
    'vinculado_por_user_id',
    'vinculado_em',
    'revertido_por_user_id',
    'revertido_em',
    'motivo_reversao',
    'lock_token',
])]
class TefLiquidacaoLoteExtrato extends Model
{
    public const STATUS_ATIVO = 'ativo';

    public const STATUS_REVERTIDO = 'revertido';

    protected $table = 'tef_liquidacao_lote_extratos';

    protected function casts(): array
    {
        return [
            'valor_vinculado' => 'decimal:2',
            'valor_lote_liquido_ref' => 'decimal:2',
            'valor_extrato_ref' => 'decimal:2',
            'diferenca' => 'decimal:2',
            'vinculado_em' => 'datetime',
            'revertido_em' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TefLiquidacaoLote::class, 'lote_id');
    }

    public function extratoLancamento(): BelongsTo
    {
        return $this->belongsTo(ExtratoLancamento::class, 'extrato_lancamento_id');
    }

    public function vinculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vinculado_por_user_id');
    }

    public function revertidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertido_por_user_id');
    }

    public function isAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }
}
