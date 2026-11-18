<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fabricacao extends Model
{
    public const STATUS_FINALIZADA = 'finalizada';

    /** Reservado Etapa 3. */
    public const STATUS_ESTORNADA = 'estornada';

    protected $table = 'fabricacoes';

    protected $fillable = [
        'empresa_id',
        'numero',
        'data_hora',
        'product_id',
        'quantidade',
        'estoque_id',
        'usuario_id',
        'custo_total',
        'custo_unitario',
        'status',
        'idempotencia_token',
        'rendimento_receita',
        'fator',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'quantidade' => 'decimal:3',
            'custo_total' => 'decimal:2',
            'custo_unitario' => 'decimal:2',
            'rendimento_receita' => 'decimal:3',
            'fator' => 'decimal:8',
        ];
    }

    public function isFinalizada(): bool
    {
        return $this->status === self::STATUS_FINALIZADA;
    }

    public function isEstornada(): bool
    {
        return $this->status === self::STATUS_ESTORNADA;
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FabricacaoItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
