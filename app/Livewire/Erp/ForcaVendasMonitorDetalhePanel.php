<?php

namespace App\Livewire\Erp;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns\ManagesForcaVendasMonitorMargem;
use App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns\ProvidesForcaVendasMonitorSelecaoUi;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Painel inferior do Monitor FV (itens + pagamentos + barra de ações).
 * Isolado do List page para a seleção não remontar a EmbeddedTable.
 */
class ForcaVendasMonitorDetalhePanel extends Component
{
    use ManagesForcaVendasMonitorMargem;
    use ProvidesForcaVendasMonitorSelecaoUi;

    /** @var array<int, string> */
    public array $selecionados = [];

    public ?int $highlightedRecordId = null;

    public bool $nfeLoteProgressOpen = false;

    public bool $faturarProgressOpen = false;

    public bool $cancelarProgressOpen = false;

    public bool $reabrirProgressOpen = false;

    public bool $clonarProgressOpen = false;

    /**
     * @param  array<int, string|int>  $selecionados
     */
    #[On('fv-monitor-selecao')]
    public function syncSelecao(
        array $selecionados = [],
        ?int $highlightedRecordId = null,
        bool $nfeLoteProgressOpen = false,
        bool $faturarProgressOpen = false,
        bool $cancelarProgressOpen = false,
        bool $reabrirProgressOpen = false,
        bool $clonarProgressOpen = false,
    ): void {
        $this->selecionados = array_values(array_map(
            static fn ($id): string => (string) $id,
            $selecionados,
        ));
        $this->highlightedRecordId = $highlightedRecordId;
        $this->nfeLoteProgressOpen = $nfeLoteProgressOpen;
        $this->faturarProgressOpen = $faturarProgressOpen;
        $this->cancelarProgressOpen = $cancelarProgressOpen;
        $this->reabrirProgressOpen = $reabrirProgressOpen;
        $this->clonarProgressOpen = $clonarProgressOpen;

        if ($this->margemModalOpen) {
            $this->fecharMargemVenda();
        }

        unset(
            $this->selecionado,
            $this->itensSelecionado,
            $this->pagamentosSelecionado,
            $this->exibirCustoProdutoGrade,
            $this->custosUnitariosGrade,
            $this->nfeEmitirEstado,
            $this->nfeAbrirEstado,
            $this->faturarEstado,
            $this->reabrirEstado,
            $this->telaVendaEstado,
            $this->enviarEstado,
            $this->imprimirEstado,
            $this->cancelarEstado,
        );
        $this->esquecerSituacoesSelecaoBarra();
    }

    public function render(): View
    {
        return view('livewire.erp.forca-vendas-monitor-detalhe-panel');
    }
}
