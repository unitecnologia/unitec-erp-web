<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioContagemItem extends Model
{
    public const SITUACAO_PENDENTE = 'pendente';

    public const SITUACAO_APLICADO = 'aplicado';

    protected $table = 'inventario_contagem_itens';

    protected $fillable = [
        'inventario_contagem_id',
        'inventario_etapa_id',
        'product_id',
        'user_id',
        'codigo',
        'descricao',
        'unidade',
        'quantidade_contada',
        'saldo_referencia',
        'movimento_referencia_id',
        'diferenca',
        'saldo_antes',
        'saldo_depois',
        'ajuste_estoque_id',
        'idempotencia',
        'contado_em',
        'situacao',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_contada' => 'decimal:3',
            'saldo_referencia' => 'decimal:3',
            'movimento_referencia_id' => 'integer',
            'diferenca' => 'decimal:3',
            'saldo_antes' => 'decimal:3',
            'saldo_depois' => 'decimal:3',
            'contado_em' => 'datetime',
        ];
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(InventarioEtapa::class, 'inventario_etapa_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contagem(): BelongsTo
    {
        return $this->belongsTo(InventarioContagem::class, 'inventario_contagem_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(AjusteEstoque::class, 'ajuste_estoque_id');
    }
}
