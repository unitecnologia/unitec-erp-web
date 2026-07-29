<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEmpresaPreco extends Model
{
    protected $table = 'product_empresa_precos';

    protected $fillable = [
        'product_id',
        'empresa_id',
        'preco_compra',
        'pct_custos',
        'preco_custo',
        'pct_lucro',
        'preco_venda',
        'preco_atacado',
        'preco_especial',
    ];

    protected function casts(): array
    {
        return [
            'preco_compra' => 'decimal:2',
            'pct_custos' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'pct_lucro' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'preco_atacado' => 'decimal:2',
            'preco_especial' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
