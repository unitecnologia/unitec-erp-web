<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaEspera extends Model
{
    protected $table = 'pdv_venda_esperas';

    protected $fillable = [
        'pdv_caixa_sessao_id',
        'user_id',
        'vendedor_id',
        'sequencia',
        'cliente_nome',
        'vendedor_nome',
        'qtd_itens',
        'total',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function caixaSessao(): BelongsTo
    {
        return $this->belongsTo(PdvCaixaSessao::class, 'pdv_caixa_sessao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }
}
