<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'empresa_id',
    'cfop_financeiro_estadual',
    'cfop_financeiro_interestadual',
    'cfop_venda_mercadoria_estadual',
    'cfop_venda_mercadoria_interestadual',
    'cfop_acompanhamento_estadual',
    'cfop_acompanhamento_interestadual',
    'cfop_devolucao_vendas_estadual',
    'cfop_devolucao_vendas_interestadual',
    'cfop_devolucao_compras_estadual',
    'cfop_devolucao_compras_interestadual',
    'cfop_transferencias_estadual',
    'cfop_transferencias_interestadual',
    'cfop_outras_saidas_estadual',
    'cfop_outras_saidas_interestadual',
    'cfop_entrada_futura_estadual',
    'cfop_entrada_futura_interestadual',
    'cfop_bonificacao_estadual',
    'cfop_bonificacao_interestadual',
    'cfop_saida_perda_estadual',
    'cfop_saida_perda_interestadual',
    'mensagem',
])]
class OperacaoFiscal extends Model
{
    protected $table = 'operacoes_fiscais';

    public static function forEmpresa(int $empresaId): self
    {
        return static::query()->firstOrCreate(['empresa_id' => $empresaId]);
    }

    public function cfopSaidaPerda(bool $interestadual): ?int
    {
        $column = $interestadual
            ? 'cfop_saida_perda_interestadual'
            : 'cfop_saida_perda_estadual';

        $cfop = (int) ($this->{$column} ?? 0);

        return $cfop > 0 ? $cfop : null;
    }

    public function cfopDevolucaoCompras(bool $interestadual): ?int
    {
        $column = $interestadual
            ? 'cfop_devolucao_compras_interestadual'
            : 'cfop_devolucao_compras_estadual';

        $cfop = (int) ($this->{$column} ?? 0);

        return $cfop > 0 ? $cfop : null;
    }

    public function cfopVendaMercadoria(bool $interestadual): ?int
    {
        $column = $interestadual
            ? 'cfop_venda_mercadoria_interestadual'
            : 'cfop_venda_mercadoria_estadual';

        $cfop = (int) ($this->{$column} ?? 0);

        return $cfop > 0 ? $cfop : null;
    }
}
