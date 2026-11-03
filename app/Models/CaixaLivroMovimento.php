<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'caixa_conta_id',
    'status',
    'aberto_em',
    'aberto_por',
    'saldo_abertura',
    'troco_inicial',
    'observacao_abertura',
    'fechado_em',
    'fechado_por',
    'total_entradas',
    'total_saidas',
    'total_transferencias_entrada',
    'total_transferencias_saida',
    'saldo_calculado',
    'valor_contado',
    'diferenca',
    'conferencia_formas',
    'total_informado',
    'observacao_fechamento',
    'reaberto_em',
    'reaberto_por',
    'motivo_reabertura',
])]
class CaixaLivroMovimento extends Model
{
    public const STATUS_ABERTO = 'aberto';

    public const STATUS_FECHADO = 'fechado';

    protected $table = 'caixa_livro_movimentos';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    public function reabertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reaberto_por');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CaixaLivroMovimentoEvento::class, 'caixa_livro_movimento_id');
    }

    public function isAberto(): bool
    {
        return (string) $this->status === self::STATUS_ABERTO;
    }

    public function isFechado(): bool
    {
        return (string) $this->status === self::STATUS_FECHADO;
    }

    protected function casts(): array
    {
        return [
            'aberto_em' => 'datetime',
            'fechado_em' => 'datetime',
            'reaberto_em' => 'datetime',
            'saldo_abertura' => 'decimal:2',
            'troco_inicial' => 'decimal:2',
            'total_entradas' => 'decimal:2',
            'total_saidas' => 'decimal:2',
            'total_transferencias_entrada' => 'decimal:2',
            'total_transferencias_saida' => 'decimal:2',
            'saldo_calculado' => 'decimal:2',
            'valor_contado' => 'decimal:2',
            'diferenca' => 'decimal:2',
            'conferencia_formas' => 'array',
            'total_informado' => 'decimal:2',
        ];
    }
}
