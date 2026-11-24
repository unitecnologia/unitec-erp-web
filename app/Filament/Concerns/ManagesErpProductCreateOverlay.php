<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\ProductResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;

/**
 * Cadastro rápido de produto via overlay (CreateProduct ?orcamento=1&os_embed=1).
 * Cada tela implementa applyProductCreatedFromOverlay() para preparar o item sem recarregar o formulário.
 */
trait ManagesErpProductCreateOverlay
{
    public bool $overlayProductOpen = false;

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create').'?orcamento=1&os_embed=1';
    }

    public function openProdutosCadastro(): void
    {
        if (! $this->canOpenProductCreateOverlay()) {
            return;
        }

        if ($this->overlayProductOpen) {
            return;
        }

        if (method_exists($this, 'productCreateOverlayScreenTitle')) {
            ErpScreen::set((string) $this->productCreateOverlayScreenTitle());
        } else {
            ErpScreen::set('Cadastro de Produtos');
        }

        if (property_exists($this, 'overlayPersonOpen')) {
            $this->overlayPersonOpen = false;
        }

        $this->overlayProductOpen = true;
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        $this->afterProductOverlayClosed();
    }

    public function applyOverlayProdutoSaved(string $codigo): void
    {
        $this->overlayProductOpen = false;
        $this->applyProductCreatedFromOverlay($codigo);
        $this->afterProductOverlayClosed();
    }

    /**
     * Bloqueio contextual (modal fechado, readonly, sem permissão de fluxo, etc.).
     * A autorização de produtos continua em CreateProduct::mount().
     */
    abstract protected function canOpenProductCreateOverlay(): bool;

    abstract protected function applyProductCreatedFromOverlay(string $codigo): void;

    protected function afterProductOverlayClosed(): void
    {
        //
    }

    protected function notifyProductCreateBlocked(string $title): void
    {
        Notification::make()
            ->title($title)
            ->warning()
            ->send();
    }
}
