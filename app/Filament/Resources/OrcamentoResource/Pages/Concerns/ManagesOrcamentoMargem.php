<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Filament\Notifications\Notification;

/**
 * Mais opções → custo na grade + modal “Margem da venda” no Lançamento de Orçamento.
 * Reutiliza ForcaVendasMargemVendaCalculator (mesma regra da Tela de Venda).
 * Só calcula/consulta custo sob demanda (toggle ou abertura do modal).
 */
trait ManagesOrcamentoMargem
{
    public bool $margemModalOpen = false;

    /** Toggle de sessão: coluna Custo na grade (só se a empresa permitir). */
    public bool $orcExibirCustoProdutos = false;

    /**
     * @var list<array{
     *   codigo: string,
     *   produto: string,
     *   qtd: float,
     *   valor_vendido: float,
     *   desc_acr: float,
     *   liquido: float,
     *   custo: float,
     *   lucro: float,
     *   margem: float
     * }>
     */
    public array $margemLinhas = [];

    /**
     * @var array{
     *   venda_liquida: float,
     *   custo_total: float,
     *   lucro_estimado: float,
     *   margem: float
     * }
     */
    public array $margemTotais = [
        'venda_liquida' => 0.0,
        'custo_total' => 0.0,
        'lucro_estimado' => 0.0,
        'margem' => 0.0,
    ];

    public function orcamentoPermitirVisualizarMargem(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_orcamento_permitir_visualizar_margem ?? false);
    }

    public function orcamentoExibirCustoProdutoGrade(): bool
    {
        return $this->orcamentoPermitirVisualizarMargem() && $this->orcExibirCustoProdutos;
    }

    /**
     * Custos unitários em lote — só quando o usuário pediu exibir na grade.
     *
     * @return array<int, float>
     */
    public function custosUnitariosGradeOrcamento(): array
    {
        if (! $this->orcamentoExibirCustoProdutoGrade() || $this->itens === []) {
            return [];
        }

        $ids = [];

        foreach ($this->itens as $i) {
            if (! empty($i['is_separador'])) {
                continue;
            }

            $productId = (int) ($i['product_id'] ?? 0);

            if ($productId > 0) {
                $ids[] = $productId;
            }
        }

        return ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            $ids,
            ErpContext::currentEmpresaId(),
        );
    }

    public function toggleExibirCustoProdutosOrcamento(): void
    {
        if (! $this->orcamentoPermitirVisualizarMargem()) {
            $this->orcExibirCustoProdutos = false;

            return;
        }

        $this->orcExibirCustoProdutos = ! $this->orcExibirCustoProdutos;
    }

    public function abrirMargemVenda(): void
    {
        if (! $this->orcamentoPermitirVisualizarMargem()) {
            return;
        }

        if ($this->itens === []) {
            Notification::make()
                ->title('Inclua itens no orçamento para ver a margem.')
                ->warning()
                ->send();

            return;
        }

        $itens = $this->itensParaCalculoMargem();
        $productIds = array_map(
            static fn (array $i): int => (int) ($i['product_id'] ?? 0),
            $itens,
        );

        $custos = ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            $productIds,
            ErpContext::currentEmpresaId(),
        );

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $itens,
            ErpMoney::parseBr($this->descontoValorDisplay),
            0.0,
            $custos,
        );

        $this->margemLinhas = $resultado['linhas'];
        $this->margemTotais = $resultado['totais'];
        $this->margemModalOpen = true;
    }

    public function fecharMargemVenda(): void
    {
        $this->margemModalOpen = false;
        $this->margemLinhas = [];
        $this->margemTotais = [
            'venda_liquida' => 0.0,
            'custo_total' => 0.0,
            'lucro_estimado' => 0.0,
            'margem' => 0.0,
        ];
    }

    /**
     * Normaliza linhas do orçamento (strings BR / product_codigo) para o calculador da TV.
     *
     * @return list<array{
     *   product_id: int,
     *   codigo: string,
     *   descricao: string,
     *   quantidade: float,
     *   preco_unitario: float,
     *   desconto: float,
     *   acrescimo: float,
     *   total: float
     * }>
     */
    protected function itensParaCalculoMargem(): array
    {
        $out = [];

        foreach ($this->itens as $item) {
            if (! empty($item['is_separador'])) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $out[] = [
                'product_id' => $productId,
                'codigo' => (string) ($item['product_codigo'] ?? $item['codigo'] ?? ''),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'quantidade' => ErpMoney::parseBr($item['quantidade'] ?? 0, 3),
                'preco_unitario' => ErpMoney::parseBr($item['preco_unitario'] ?? 0),
                'desconto' => ErpMoney::parseBr($item['desconto'] ?? 0),
                'acrescimo' => ErpMoney::parseBr($item['acrescimo'] ?? 0),
                'total' => ErpMoney::parseBr($item['total'] ?? 0),
            ];
        }

        return $out;
    }
}
