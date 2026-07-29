<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'banco', 'layout', 'carteira', 'tipo_carteira', 'especie_docto', 'especie_moeda',
    'aceite', 'tipo_documento', 'tipo_distribuicao', 'carac_titulo', 'responsavel_emissao',
    'cnab_versao', 'cnab_lv_lote', 'cnab_lv_arquivo', 'cnab_codigo_transmissao', 'cnab_densidade_gravacao',
    'cnab_prefixo_remessa', 'cip', 'homologacao', 'imp_msg_padrao_comp_banco', 'ler_cedente_arq_retorno',
    'ler_nosso_num_completo', 'remover_acentuacao_remessa', 'imp_verso_fatura', 'local_pagamento',
    'ben_agencia', 'ben_agencia_dv', 'ben_conta', 'ben_conta_dv', 'ben_agencia_conta_dv',
    'ben_convenio', 'ben_modalidade', 'ben_operacao', 'ben_cod_cedente', 'nosso_numero',
    'webservice_client_id', 'webservice_client_secret', 'webservice_key_user', 'webservice_indicador_pix',
    'webservice_ssl_lib', 'path_remessa', 'path_retorno', 'codigo_legado',
])]
class BoletoParametro extends Model
{
    protected $table = 'boleto_parametros';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function forEmpresa(int $empresaId): self
    {
        return static::query()->firstOrCreate(
            ['empresa_id' => $empresaId],
            ['homologacao' => true, 'remover_acentuacao_remessa' => true],
        );
    }

    protected function casts(): array
    {
        return [
            'homologacao' => 'boolean',
            'imp_msg_padrao_comp_banco' => 'boolean',
            'ler_cedente_arq_retorno' => 'boolean',
            'ler_nosso_num_completo' => 'boolean',
            'remover_acentuacao_remessa' => 'boolean',
            'imp_verso_fatura' => 'boolean',
            'webservice_indicador_pix' => 'boolean',
        ];
    }
}
