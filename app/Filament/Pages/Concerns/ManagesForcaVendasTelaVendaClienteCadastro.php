<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Resources\PersonResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;

/**
 * Cadastro rápido de cliente na Tela de Venda — mesmo overlay/embed da OS (?tipo=clientes&orcamento=1&os_embed=1).
 * os_embed: altura ao conteúdo (evita faixa estreita com formulário oculto).
 */
trait ManagesForcaVendasTelaVendaClienteCadastro
{
    public bool $overlayPersonOpen = false;

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create').'?tipo=clientes&orcamento=1&os_embed=1';
    }

    public function openPessoasCadastro(): void
    {
        if ($this->etapa !== 'venda') {
            Notification::make()
                ->title('Volte para a venda para cadastrar um cliente.')
                ->warning()
                ->send();

            return;
        }

        if (
            $this->descontoModalOpen
            || $this->excluirItemModalOpen
            || $this->fvImportarOrcamentoOpen
            || $this->fvListarVendasOpen
            || $this->tabPrecoItemModalOpen
            || $this->margemModalOpen
            || $this->observacaoPedidoModalOpen
            || $this->observacaoClienteModalOpen
            || $this->fvSairConfirmOpen
            || $this->overlayPersonOpen
            || $this->overlayProductOpen
        ) {
            return;
        }

        ErpScreen::set('Cadastro de Pessoas');
        $this->overlayProductOpen = false;
        $this->overlayPersonOpen = true;
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        ErpScreen::set('Tela de Venda');
        $this->dispatch('fv-tela-venda-focus-cliente');
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $this->overlayPersonOpen = false;
        ErpScreen::set('Tela de Venda');

        if ($clienteId <= 0) {
            $this->dispatch('fv-tela-venda-focus-cliente');

            return;
        }

        // Mesma seleção da pesquisa: preserva itens, descontos, vendedor e demais dados.
        // Sem skipRender: o remorph remove o iframe do cadastro neste mesmo request.
        $this->selecionarCliente($clienteId);
    }
}
