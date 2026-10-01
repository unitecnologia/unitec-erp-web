<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaPedido extends Model
{
    protected $table = 'carga_pedidos';

    protected $fillable = [
        'carga_id',
        'pedido_id',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'pedido_id');
    }
}
