<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLoteMovimento extends Model
{
    public const SENTIDO_SAIDA = 'saida';

    public const SENTIDO_ENTRADA = 'entrada';

    protected $table = 'product_lote_movimentos';

    protected $fillable = [
        'product_id',
        'product_lote_id',
        'lote',
        'data_validade',
        'quantidade',
        'sentido',
        'origem_tipo',
        'origem_id',
        'referencia_movimento_id',
    ];

    protected function casts(): array
    {
        return [
            'data_validade' => 'date',
            'quantidade' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLote(): BelongsTo
    {
        return $this->belongsTo(ProductLote::class, 'product_lote_id');
    }
}
