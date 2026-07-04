<?php

namespace App\Filament\Resources\VendedorResource\Pages\Concerns;

use App\Models\Vendedor;
use Filament\Notifications\Notification;

trait ManagesVendedorDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteVendedor(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = Vendedor::query()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Vendedor não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->deleteConfirmRecordId = $record->getKey();
        $this->deleteConfirmNome = (string) $record->nome;
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteVendedor(): void
    {
        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteVendedor();

            return;
        }

        Vendedor::query()->whereKey($this->deleteConfirmRecordId)->delete();

        $this->cancelDeleteVendedor();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Vendedor excluído.')
            ->success()
            ->send();
    }

    public function cancelDeleteVendedor(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
