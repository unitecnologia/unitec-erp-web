<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'descricao',
    'conta_destino_id',
    'tipo',
    'taxa_cartao',
    'prazo_cartao',
    'max_parcelas',
    'intervalo_parcelas',
    'atalho',
    'tipo_movimento',
    'usa_tef',
    'usa_super_tef',
    'aparece_venda',
    'aparece_contas_receber',
    'nfce',
    'disponivel_mobile',
    'parcelas',
    'ativo',
])]
class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected function casts(): array
    {
        return [
            'codigo' => 'integer',
            'conta_destino_id' => 'integer',
            'taxa_cartao' => 'decimal:2',
            'prazo_cartao' => 'integer',
            'max_parcelas' => 'integer',
            'intervalo_parcelas' => 'integer',
            'usa_tef' => 'boolean',
            'usa_super_tef' => 'boolean',
            'aparece_venda' => 'boolean',
            'aparece_contas_receber' => 'boolean',
            'nfce' => 'boolean',
            'disponivel_mobile' => 'boolean',
            'parcelas' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function contaDestino(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'conta_destino_id');
    }

    public function tabelasPrazo(): HasMany
    {
        return $this->hasMany(TabelaPrazo::class)->orderBy('ordem');
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            'dinheiro' => 'Dinheiro',
            'pix' => 'PIX',
            'cartao_debito' => 'Cartão de Débito',
            'cartao_credito' => 'Cartão de Crédito',
            'deposito' => 'Depósito',
            'tef' => 'TEF',
            'cheque' => 'Cheque',
            'boleto' => 'Boleto',
            'crediario' => 'Crediário',
            'troca' => 'Troca',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tipoMovimentoLabels(): array
    {
        return [
            'caixa' => 'Caixa',
            'contas_receber' => 'Contas à Receber',
            'ficha_cliente' => 'Ficha Cliente',
            'troca' => 'Troca',
            'deposito' => 'Depósito',
            'nenhum' => 'Nenhum',
        ];
    }
}
