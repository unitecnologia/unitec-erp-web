<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns;

use App\Support\Erp\ErpContext;
use App\Support\ForcaVendas\ForcaVendasMargemVendaCalculator;
use Filament\Notifications\Notification;

/**
 * Modal “Margem da venda” no Monitor (painel de detalhe).
 * Calcula só ao abrir; não altera o pedido.
 */
trait ManagesForcaVendasMonitorMargem
{
    public bool $margemModalOpen = false;

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

    public function abrirMargemVenda(): void
    {
        if (count($this->selecionados) !== 1) {
            Notification::make()
                ->title('Selecione exatamente um pedido para ver a margem.')
                ->warning()
                ->send();

            return;
        }

        $itens = $this->itensSelecionado;

        if ($itens === []) {
            Notification::make()
                ->title('Pedido sem itens para calcular a margem.')
                ->warning()
                ->send();

            return;
        }

        $pedido = $this->selecionado?->pedido;
        $somaItens = round(array_sum(array_map(
            static fn (array $i): float => (float) ($i['total'] ?? 0),
            $itens,
        )), 2);

        $totalPedido = round((float) ($pedido?->total ?? 0), 2);
        $residual = ForcaVendasMargemVendaCalculator::residualCabecalho($somaItens, $totalPedido);

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
            $residual['desconto'],
            $residual['acrescimo'],
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
