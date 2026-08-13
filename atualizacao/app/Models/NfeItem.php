<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nfe_id',
    'item',
    'product_id',
    'cod_barra',
    'ncm',
    'cfop',
    'cst',
    'csosn',
    'cest',
    'unidade',
    'descricao',
    'descricao_complementar',
    'info_adicionais',
    'quantidade',
    'valor_unitario',
    'total',
    'situacao',
    'desconto',
    'frete',
    'seguro',
    'despesas',
    'outros',
    'base_icms',
    'aliq_icms',
    'valor_icms',
    'base_icms_st',
    'aliq_icms_st',
    'valor_icms_st',
    'cst_ipi',
    'base_ipi',
    'aliq_ipi',
    'valor_ipi',
    'cst_pis',
    'base_pis_icms',
    'aliq_pis_icms',
    'valor_pis_icms',
    'cst_cofins',
    'base_cofins_icms',
    'aliq_cofins_icms',
    'valor_cofins_icms',
    'trib_mun',
    'trib_est',
    'trib_fed',
    'trib_imp',
    'vbcufdest',
    'vicmsufdest',
    'vicmsufremet',
    'vfcp',
    'motivo_desoneracao',
    'base_desoneracao',
    'desc_desoneracao',
    'valor_desoneracao',
    'class_trib',
    'cst_ibs_cbs',
    'v_ibs_mun',
    'v_ibs_uf',
    'v_cbs',
    'bc_ibs',
    'alq_cbs',
    'alq_ibs_mun',
    'alq_ibs_uf',
])]
class NfeItem extends Model
{
    protected $table = 'nfe_itens';

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
            'valor_unitario' => 'decimal:4',
            'total' => 'decimal:2',
            'desconto' => 'decimal:2',
            'frete' => 'decimal:2',
            'seguro' => 'decimal:2',
            'despesas' => 'decimal:2',
            'outros' => 'decimal:2',
            'base_icms' => 'decimal:2',
            'aliq_icms' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'base_icms_st' => 'decimal:2',
            'aliq_icms_st' => 'decimal:2',
            'valor_icms_st' => 'decimal:2',
            'base_ipi' => 'decimal:2',
            'aliq_ipi' => 'decimal:2',
            'valor_ipi' => 'decimal:2',
            'base_pis_icms' => 'decimal:2',
            'aliq_pis_icms' => 'decimal:2',
            'valor_pis_icms' => 'decimal:2',
            'base_cofins_icms' => 'decimal:2',
            'aliq_cofins_icms' => 'decimal:2',
            'valor_cofins_icms' => 'decimal:2',
            'base_desoneracao' => 'decimal:2',
            'desc_desoneracao' => 'decimal:2',
            'valor_desoneracao' => 'decimal:2',
            'v_ibs_mun' => 'decimal:2',
            'v_ibs_uf' => 'decimal:2',
            'v_cbs' => 'decimal:2',
            'bc_ibs' => 'decimal:2',
            'alq_cbs' => 'decimal:4',
            'alq_ibs_mun' => 'decimal:4',
            'alq_ibs_uf' => 'decimal:4',
        ];
    }
}
