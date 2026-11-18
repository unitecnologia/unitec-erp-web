<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpOperacaoLog extends Model
{
    protected $table = 'erp_operacao_logs';

    protected $fillable = [
        'ocorrido_em',
        'user_id',
        'user_nome',
        'empresa_id',
        'operacao',
        'origem',
        'documento_tipo',
        'documento_id',
        'documento_numero',
        'resultado',
        'resumo',
        'detalhes',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
            'detalhes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Isolamento multiempresa — lista e detalhes da empresa ativa.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDaEmpresaAtiva(Builder $query, ?int $empresaId = null): Builder
    {
        $id = (int) ($empresaId ?? 0);

        if ($id <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->getTable().'.empresa_id', $id);
    }

    /**
     * Colunas da listagem — sem JSON detalhes (carregado só no modal).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeParaListagem(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->select([
            "{$table}.id",
            "{$table}.ocorrido_em",
            "{$table}.user_id",
            "{$table}.user_nome",
            "{$table}.empresa_id",
            "{$table}.operacao",
            "{$table}.origem",
            "{$table}.documento_tipo",
            "{$table}.documento_id",
            "{$table}.documento_numero",
            "{$table}.resultado",
            "{$table}.resumo",
            "{$table}.created_at",
            "{$table}.updated_at",
        ]);
    }
}
