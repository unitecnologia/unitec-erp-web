<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvVendaEspera extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_EM_USO = 'em_uso';

    public const STATUS_FINALIZADA = 'finalizada';

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
        'status',
        'finalizada_em',
        'pdv_venda_id',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'finalizada_em' => 'datetime',
        ];
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', self::STATUS_PENDENTE)
                ->orWhereNull('status');
        });
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

    public function pdvVenda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }
}
