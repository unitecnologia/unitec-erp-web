<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva ativa de venda comissionada (unique venda_id) — anti-duplicidade sob concorrência.
 */
class ComissaoVendaAtiva extends Model
{
    protected $table = 'comissao_venda_ativa';

    protected $fillable = [
        'empresa_id',
        'vendedor_id',
        'venda_id',
        'comissao_periodo_id',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(ComissaoPeriodo::class, 'comissao_periodo_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }
}
