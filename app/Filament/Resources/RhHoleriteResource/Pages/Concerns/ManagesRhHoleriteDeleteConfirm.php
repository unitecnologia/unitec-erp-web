<?php

namespace App\Filament\Resources\RhHoleriteResource\Pages\Concerns;

use App\Models\RhHolerite;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhHoleriteService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesRhHoleriteDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteHolerite(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.holerites.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhHolerite::query()->with('funcionario')->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Holerite não encontrado.')->warning()->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->getKey();
        $this->deleteConfirmNome = trim($record->competenciaLabel().' — '.($record->funcionario?->nome ?? ''));
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteHolerite(): void
    {
        if (! ErpAccess::currentCan('rh.holerites.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->cancelDeleteHolerite();

            return;
        }

        $record = RhHolerite::query()->find($this->deleteConfirmRecordId);
        if ($record) {
            try {
                app(RhHoleriteService::class)->inativar($record, 'Inativação pela listagem (arquivo preservado)');
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->cancelDeleteHolerite();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Holerite inativado (arquivo preservado).')->success()->send();
    }

    public function cancelDeleteHolerite(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
