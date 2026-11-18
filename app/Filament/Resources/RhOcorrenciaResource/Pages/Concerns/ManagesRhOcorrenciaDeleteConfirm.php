<?php

namespace App\Filament\Resources\RhOcorrenciaResource\Pages\Concerns;

use App\Models\RhOcorrencia;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhOcorrenciaService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesRhOcorrenciaDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public string $cancelMotivo = '';

    public function cancelarOcorrencia(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.ocorrencias.delete')) {
            Notification::make()->title('Sem permissão para cancelar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhOcorrencia::query()->with('funcionario')->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Ocorrência não encontrada.')->warning()->send();

            return;
        }

        if ($record->isCancelada()) {
            Notification::make()->title('Ocorrência já está cancelada.')->warning()->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->getKey();
        $this->deleteConfirmNome = trim($record->tipoLabel().' — '.($record->funcionario?->nome ?? ''));
        $this->cancelMotivo = '';
        $this->deleteConfirmOpen = true;
    }

    public function confirmCancelarOcorrencia(): void
    {
        if (! ErpAccess::currentCan('rh.ocorrencias.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->cancelDeleteOcorrencia();

            return;
        }

        $record = RhOcorrencia::query()->find($this->deleteConfirmRecordId);
        if ($record) {
            try {
                app(RhOcorrenciaService::class)->cancelar(
                    $record,
                    filled($this->cancelMotivo) ? $this->cancelMotivo : 'Cancelamento pela listagem'
                );
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->cancelDeleteOcorrencia();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Ocorrência cancelada (histórico preservado).')->success()->send();
    }

    public function cancelDeleteOcorrencia(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
        $this->cancelMotivo = '';
    }
}
