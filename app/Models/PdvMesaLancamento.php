<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lançamento do app Unitec Mesas já aplicado numa mesa do PDV.
 * O `uuid` gerado no celular garante que um reenvio não duplique os itens.
 */
class PdvMesaLancamento extends Model
{
    protected $table = 'pdv_mesa_lancamentos';

    protected $fillable = [
        'uuid',
        'empresa_id',
        'numero',
        'pdv_mesa_id',
        'user_id',
        'device_uuid',
        'qtd_itens',
        'total',
        'itens',
    ];

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'numero' => 'integer',
            'pdv_mesa_id' => 'integer',
            'user_id' => 'integer',
            'qtd_itens' => 'integer',
            'total' => 'decimal:2',
        ];
    }
}
