<?php

namespace App\Models;

use App\Support\Fiscal\FiscalOperationDefaults;
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
    'cfop_entrega_futura_estadual',
    'cfop_entrega_futura_interestadual',
    'cfop_bonificacao_estadual',
    'cfop_bonificacao_interestadual',
    'cfop_saida_perda_estadual',
    'cfop_saida_perda_interestadual',
    'mensagem',
])]
class OperacaoFiscal extends Model
{
    protected $table = 'operacoes_fiscais';

    /**
     * Empresa nova recebe os CFOPs padrão. Empresa existente nunca é sobrescrita.
     */
    public static function forEmpresa(int $empresaId): self
    {
        return static::query()->firstOrCreate(
            ['empresa_id' => $empresaId],
            FiscalOperationDefaults::attributesForCreate()
        );
    }

    /**
     * CFOP configurado na empresa ou, se vazio, o padrão Unitec da operação.
     * Não força o padrão quando a empresa já salvou um valor.
     */
    public function cfopOperacao(string $operacao, bool $interestadual): ?int
    {
        if ($interestadual && ! FiscalOperationDefaults::interestadualAplicavel($operacao)) {
            return null;
        }

        $column = FiscalOperationDefaults::column(
            $operacao,
            $interestadual ? 'interestadual' : 'estadual'
        );

        $cfop = (int) ($this->{$column} ?? 0);
        if ($cfop > 0) {
            return $cfop;
        }

        return FiscalOperationDefaults::defaultCfop(
            $operacao,
            $interestadual ? 'interestadual' : 'estadual'
        );
    }

    /**
     * Valor persistido (sem fallback). Útil para a tela de configuração.
     */
    public function cfopSalvo(string $operacao, string $escopo): ?int
    {
        $column = FiscalOperationDefaults::column($operacao, $escopo);
        $cfop = (int) ($this->{$column} ?? 0);

        return $cfop > 0 ? $cfop : null;
    }

    public function cfopSaidaPerda(bool $interestadual): ?int
    {
        return $this->cfopOperacao('saida_perda', $interestadual);
    }

    public function cfopDevolucaoCompras(bool $interestadual): ?int
    {
        return $this->cfopOperacao('devolucao_compras', $interestadual);
    }

    public function cfopVendaMercadoria(bool $interestadual): ?int
    {
        return $this->cfopOperacao('venda_mercadoria', $interestadual);
    }

    public function restaurarPadroesUnitec(): void
    {
        $this->fill(FiscalOperationDefaults::attributesForRestore());
        $this->save();
    }
}
