<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalClassificacaoTributaria extends Model
{
    protected $table = 'fiscal_classificacoes_tributarias';

    protected $fillable = [
        'codigo',
        'cst_ibs_cbs',
        'cst_descricao',
        'descricao',
        'nome_reduzido',
        'ind_nfe',
        'ind_nfce',
        'ind_nfse',
        'ind_cte',
        'vigencia_inicio',
        'vigencia_fim',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ind_nfe' => 'boolean',
            'ind_nfce' => 'boolean',
            'ind_nfse' => 'boolean',
            'ind_cte' => 'boolean',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'meta' => 'array',
        ];
    }
}
