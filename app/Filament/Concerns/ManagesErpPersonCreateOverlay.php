<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\PersonResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;

/**
 * Cadastro rápido de cliente via overlay (CreatePerson ?tipo=clientes&orcamento=1&os_embed=1).
 * Cada tela implementa applyPersonCreatedFromOverlay() para selecionar o cliente sem recarregar o formulário.
 */
trait ManagesErpPersonCreateOverlay
{
    public bool $overlayPersonOpen = false;

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create').'?tipo=clientes&orcamento=1&os_embed=1';
    }

    public function openPessoasCadastro(): void
    {
        if (! $this->canOpenPersonCreateOverlay()) {
            return;
        }

        if ($this->overlayPersonOpen) {
            return;
        }

        if (method_exists($this, 'personCreateOverlayScreenTitle')) {
            ErpScreen::set((string) $this->personCreateOverlayScreenTitle());
        } else {
            ErpScreen::set('Cadastro de Pessoas');
        }

        if (property_exists($this, 'overlayProductOpen')) {
            $this->overlayProductOpen = false;
        }

        $this->overlayPersonOpen = true;
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        $this->afterPersonOverlayClosed();
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $this->overlayPersonOpen = false;

        if ($clienteId > 0) {
            $this->applyPersonCreatedFromOverlay($clienteId);
        }

        $this->afterPersonOverlayClosed();
    }

    /**
     * Bloqueio contextual (modal fechado, readonly, sem permissão de fluxo, etc.).
     * A autorização pessoas.create continua em CreatePerson::mount().
     */
    abstract protected function canOpenPersonCreateOverlay(): bool;

    abstract protected function applyPersonCreatedFromOverlay(int $clienteId): void;

    protected function afterPersonOverlayClosed(): void
    {
        //
    }

    protected function notifyPersonCreateBlocked(string $title): void
    {
        Notification::make()
            ->title($title)
            ->warning()
            ->send();
    }
}
