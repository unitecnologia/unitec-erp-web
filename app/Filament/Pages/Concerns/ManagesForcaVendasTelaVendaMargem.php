<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Erp\ErpContext;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Filament\Notifications\Notification;

/**
 * Modal “Margem da venda” + toggle de custo na grade (Mais opções) na Tela de Venda.
 * Custo/margem sob demanda; não altera $itens.
 */
trait ManagesForcaVendasTelaVendaMargem
{
    public bool $margemModalOpen = false;

    /** Toggle de sessão: coluna Custo na grade (via Mais opções). Oculto por padrão. */
    public bool $tvExibirCustoProdutos = false;

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

    public function empresaExibirMaisOpcoesNaTelaVenda(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_monitor_vendas_exibir_mais_opcoes ?? false);
    }

    public function toggleExibirCustoProdutos(): void
    {
        if (! $this->empresaExibirMaisOpcoesNaTelaVenda()) {
            $this->tvExibirCustoProdutos = false;
            unset($this->exibirCustoProdutoGrade, $this->custosUnitariosGrade);

            return;
        }

        $this->tvExibirCustoProdutos = ! $this->tvExibirCustoProdutos;
        // Invalida Computed da página para a grade refletir o toggle no mesmo request.
        unset($this->exibirCustoProdutoGrade, $this->custosUnitariosGrade);
    }

    public function abrirMargemVenda(): void
    {
        if ($this->itens === []) {
            Notification::make()
                ->title('Inclua itens na venda para ver a margem.')
                ->warning()
                ->send();

            return;
        }

        $productIds = array_map(
            static fn (array $i): int => (int) ($i['product_id'] ?? 0),
            $this->itens,
        );

        $custos = ForcaVendasMargemVendaCalculator::custosUnitariosPorProduto(
            $productIds,
            ErpContext::currentEmpresaId(),
        );

        $resultado = ForcaVendasMargemVendaCalculator::calcular(
            $this->itens,
            round($this->descontoPedidoEfetivo() + $this->valorTrocaPagamento(), 2),
            $this->acrescimoPedidoEfetivo(),
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
}
