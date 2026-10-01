<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFornecedorItem extends Model
{
    protected $table = 'nota_fornecedor_itens';

    protected $fillable = [
        'nota_fornecedor_id',
        'n_item',
        'c_prod',
        'c_ean',
        'descricao',
        'ncm',
        'cfop',
        'unidade',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'product_id',
        'fiscal_snapshot',
        'fiscal_inconsistente',
        'fiscal_snapshot_conflito',
        'fiscal_inconsistente_em',
    ];

    protected function casts(): array
    {
        return [
            'n_item' => 'integer',
            'quantidade' => 'decimal:4',
            'valor_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
            'fiscal_snapshot' => 'array',
            'fiscal_inconsistente' => 'boolean',
            'fiscal_snapshot_conflito' => 'array',
            'fiscal_inconsistente_em' => 'datetime',
        ];
    }

    public function notaFornecedor(): BelongsTo
    {
        return $this->belongsTo(NotaFornecedor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function compraItens(): HasMany
    {
        return $this->hasMany(CompraItem::class, 'nota_fornecedor_item_id');
    }

    public function estaVinculadoACompra(): bool
    {
        return $this->compraItens()->exists();
    }

    /**
     * Quantidade já devolvida (devoluções finalizadas) ligada a este item da nota.
     */
    public function quantidadeJaDevolvida(?int $excetoDevolucaoId = null): float
    {
        $compraItemIds = $this->compraItens()->pluck('id');

        if ($compraItemIds->isEmpty()) {
            return 0.0;
        }

        $query = DevolucaoCompraItem::query()
            ->whereIn('compra_item_id', $compraItemIds)
            ->whereHas('devolucao', function ($q) use ($excetoDevolucaoId): void {
                $q->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA);

                if ($excetoDevolucaoId) {
                    $q->where('id', '!=', $excetoDevolucaoId);
                }
            });

        return round((float) $query->sum('qtd'), 4);
    }

    public function quantidadeDisponivelParaDevolucao(?int $excetoDevolucaoId = null): float
    {
        $original = round((float) $this->quantidade, 4);
        $ja = $this->quantidadeJaDevolvida($excetoDevolucaoId);

        return round(max(0, $original - $ja), 4);
    }
}
