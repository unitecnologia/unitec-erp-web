<?php

namespace App\Filament\Resources\RhExameResource\Pages\Concerns;

use App\Models\RhExame;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhExameService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesRhExameDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteExame(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.exames.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhExame::query()->with('funcionario')->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Exame não encontrado.')->warning()->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->getKey();
        $this->deleteConfirmNome = trim($record->tipoLabel().' — '.($record->funcionario?->nome ?? ''));
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteExame(): void
    {
        if (! ErpAccess::currentCan('rh.exames.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->cancelDeleteExame();

            return;
        }

        $record = RhExame::query()->find($this->deleteConfirmRecordId);
        if ($record) {
            try {
                // Inativa — preserva histórico (não apaga ASO/registro).
                app(RhExameService::class)->inativar($record, 'Inativação pela listagem');
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->cancelDeleteExame();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Exame inativado (histórico preservado).')->success()->send();
    }

    public function cancelDeleteExame(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
