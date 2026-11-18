<?php

namespace App\Filament\Resources\RhDocumentoResource\Pages\Concerns;

use App\Models\RhAnexo;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use Filament\Notifications\Notification;

trait ManagesRhDocumentoDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteDocumento(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.documentos.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = RhAnexo::query()->documentos()->find($recordId);

        if (! $record) {
            Notification::make()->title('Documento não encontrado.')->warning()->send();

            return;
        }

        $funcionario = $record->anexavel;
        $nomeFunc = $funcionario?->nome ?? '';
        $this->deleteConfirmRecordId = (int) $record->getKey();
        $this->deleteConfirmNome = trim($record->titulo.($nomeFunc !== '' ? ' — '.$nomeFunc : ''));
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteDocumento(): void
    {
        if (! ErpAccess::currentCan('rh.documentos.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();
            $this->cancelDeleteDocumento();

            return;
        }

        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteDocumento();

            return;
        }

        $record = RhAnexo::query()->documentos()->find($this->deleteConfirmRecordId);

        if ($record) {
            app(RhAnexoService::class)->delete($record);
        }

        $this->cancelDeleteDocumento();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Documento excluído.')->success()->send();
    }

    public function cancelDeleteDocumento(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
