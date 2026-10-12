<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'product_id',
        'quantidade',
        'valor_item',
        'total',
        'comissao_pct',
    ];

    protected static function booted(): void
    {
        static::creating(static function (VendaItem $item): void {
            if (! Schema::hasColumn('venda_itens', 'comissao_pct')) {
                return;
            }

            // Já informado na criação: preserva (inclui 0 explícito).
            if (array_key_exists('comissao_pct', $item->getAttributes())) {
                return;
            }

            $item->comissao_pct = static::snapshotComissaoPct((int) ($item->product_id ?? 0));
        });
    }

    /**
     * % vigente do produto no momento da venda (0 se não paga comissão adicional).
     */
    public static function snapshotComissaoPct(int $productId): float
    {
        if ($productId <= 0
            || ! Schema::hasColumn('products', 'comissao_pct')
            || ! Schema::hasColumn('products', 'paga_comissao')
        ) {
            return 0.0;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->first(['paga_comissao', 'comissao_pct']);

        if (! $product || ! $product->paga_comissao) {
            return 0.0;
        }

        return max(0.0, round((float) ($product->comissao_pct ?? 0), 2));
    }

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_item' => 'decimal:2',
            'total' => 'decimal:2',
            'comissao_pct' => 'decimal:2',
        ];
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
