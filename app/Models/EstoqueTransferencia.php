<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstoqueTransferencia extends Model
{
    public const SITUACAO_RASCUNHO = 'rascunho';

    public const SITUACAO_CONFIRMADA = 'confirmada';

    public const SITUACAO_CANCELADA = 'cancelada';

    /** Reservado Etapa 2+ (não usado na Etapa 1). */
    public const SITUACAO_EM_TRANSITO = 'em_transito';

    public const SITUACAO_AGUARDANDO_RECEBIMENTO = 'aguardando_recebimento';

    public const MODO_INTERNA = 'interna';

    /** Reservado Etapa 2+ com NF-e. */
    public const MODO_ENTRE_CNPJS = 'entre_cnpjs';

    protected $table = 'estoque_transferencias';

    protected $fillable = [
        'empresa_origem_id',
        'empresa_destino_id',
        'estoque_origem_id',
        'estoque_destino_id',
        'numero',
        'situacao',
        'modo',
        'data',
        'observacoes',
        'usuario_id',
        'confirmado_em',
        'confirmado_por',
        'idempotencia_token',
        'nfe_saida_id',
        'nfe_entrada_id',
        'enviado_em',
        'recebido_em',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'confirmado_em' => 'datetime',
            'enviado_em' => 'datetime',
            'recebido_em' => 'datetime',
        ];
    }

    public static function nextNumero(int $empresaOrigemId): string
    {
        $max = static::query()
            ->where('empresa_origem_id', $empresaOrigemId)
            ->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_RASCUNHO => 'Rascunho',
            self::SITUACAO_CONFIRMADA => 'Confirmada',
            self::SITUACAO_CANCELADA => 'Cancelada',
            self::SITUACAO_EM_TRANSITO => 'Em trânsito',
            self::SITUACAO_AGUARDANDO_RECEBIMENTO => 'Aguardando recebimento',
        ];
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? (string) $this->situacao;
    }

    public function isConfirmada(): bool
    {
        return $this->situacao === self::SITUACAO_CONFIRMADA;
    }

    public function isRascunho(): bool
    {
        return $this->situacao === self::SITUACAO_RASCUNHO;
    }

    public function itens(): HasMany
    {
        return $this->hasMany(EstoqueTransferenciaItem::class)->orderBy('item');
    }

    public function empresaOrigem(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_origem_id');
    }

    public function empresaDestino(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_destino_id');
    }

    public function estoqueOrigem(): BelongsTo
    {
        return $this->belongsTo(Estoque::class, 'estoque_origem_id');
    }

    public function estoqueDestino(): BelongsTo
    {
        return $this->belongsTo(Estoque::class, 'estoque_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function confirmadoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }
}
