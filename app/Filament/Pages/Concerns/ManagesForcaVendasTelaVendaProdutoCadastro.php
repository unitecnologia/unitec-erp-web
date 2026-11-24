<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Resources\ProductResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;

/**
 * Cadastro rápido de produto na Tela de Venda — mesmo overlay/embed do Orçamento/OS (?orcamento=1&os_embed=1).
 * os_embed ativa altura ao conteúdo (sem vão branco abaixo do rodapé).
 */
trait ManagesForcaVendasTelaVendaProdutoCadastro
{
    public bool $overlayProductOpen = false;

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create').'?orcamento=1&os_embed=1';
    }

    public function openProdutosCadastro(): void
    {
        if ($this->etapa !== 'venda') {
            Notification::make()
                ->title('Volte para a venda para cadastrar um produto.')
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
            || $this->overlayProductOpen
            || $this->overlayPersonOpen
        ) {
            return;
        }

        ErpScreen::set('Cadastro de Produtos');
        $this->overlayPersonOpen = false;
        $this->overlayProductOpen = true;
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        ErpScreen::set('Tela de Venda');
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function applyOverlayProdutoSaved(string $codigo): void
    {
        $this->overlayProductOpen = false;
        ErpScreen::set('Tela de Venda');

        $codigo = mb_strtoupper(trim($codigo), 'UTF-8');

        if ($codigo === '') {
            $this->dispatch('fv-tela-venda-focus-barcode');

            return;
        }

        $product = $this->findExactProduct($codigo);

        if (! $product) {
            $this->codigoBarras = $codigo;
            Notification::make()
                ->title('Produto gravado.')
                ->body('Não foi possível localizar o código na venda. Digite Enter para buscar.')
                ->warning()
                ->send();
            $this->dispatch('fv-tela-venda-focus-barcode');

            return;
        }

        // Preserva itens/descontos/vendedor: só prepara o produto na barra de inclusão.
        // Sem skipRender: o remorph remove o iframe do cadastro neste mesmo request.
        $this->aplicarProdutoSelecionado($product, allowSkipRender: false);
    }
}
