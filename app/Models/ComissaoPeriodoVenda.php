<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComissaoPeriodoVenda extends Model
{
    protected $table = 'comissao_periodo_vendas';

    protected $fillable = [
        'comissao_periodo_id',
        'venda_id',
        'data',
        'base',
        'tipo',
        'percentual',
        'comissao',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(ComissaoPeriodo::class, 'comissao_periodo_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'base' => 'decimal:2',
            'percentual' => 'decimal:2',
            'comissao' => 'decimal:2',
        ];
    }
}
