<?php

namespace App\Filament\Resources\ContadorResource\Pages\Concerns;

use App\Models\Contador;
use Filament\Notifications\Notification;

trait ManagesContadorDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteContador(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = Contador::query()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Contador não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->deleteConfirmRecordId = $record->getKey();
        $this->deleteConfirmNome = (string) $record->nome;
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteContador(): void
    {
        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteContador();

            return;
        }

        Contador::query()->whereKey($this->deleteConfirmRecordId)->delete();

        $this->cancelDeleteContador();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Contador excluído.')
            ->success()
            ->send();
    }

    public function cancelDeleteContador(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
