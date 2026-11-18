<?php

namespace App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns;

use App\Models\RhAnexo;
use App\Models\RhEpiEntrega;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhEpiEntregaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhEpiEntregaMovimentos
{
    use WithFileUploads;

    public bool $devolverModalOpen = false;

    public ?int $devolverRecordId = null;

    public string $devolverData = '';

    public string $devolverObs = '';

    public bool $cancelConfirmOpen = false;

    public ?int $cancelRecordId = null;

    public string $cancelMotivo = '';

    public string $cancelNome = '';

    public ?TemporaryUploadedFile $comprovanteUpload = null;

    public function updatedComprovanteUpload(): void
    {
        if ($this->comprovanteUpload instanceof TemporaryUploadedFile) {
            $this->anexarComprovanteAssinado();
        }
    }

    public function openDevolverModal(): void
    {
        if (! ErpAccess::currentCan('rh.epis.update')) {
            Notification::make()->title('Sem permissão para devolver.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhEpiEntrega::query()->find($this->highlightedRecordId);
        if (! $record || $record->situacao !== RhEpiEntrega::SIT_ENTREGUE) {
            Notification::make()->title('Selecione uma entrega aberta.')->warning()->send();

            return;
        }

        $this->devolverRecordId = (int) $record->getKey();
        $this->devolverData = now('America/Sao_Paulo')->toDateString();
        $this->devolverObs = '';
        $this->devolverModalOpen = true;
    }

    public function closeDevolverModal(): void
    {
        $this->devolverModalOpen = false;
        $this->devolverRecordId = null;
        $this->devolverData = '';
        $this->devolverObs = '';
    }

    public function confirmDevolver(): void
    {
        if (! ErpAccess::currentCan('rh.epis.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->validate([
            'devolverData' => ['required', 'date'],
            'devolverObs' => ['nullable', 'string', 'max:500'],
        ], [], [
            'devolverData' => 'data da devolução',
        ]);

        $record = RhEpiEntrega::query()->find($this->devolverRecordId);
        if (! $record) {
            $this->closeDevolverModal();

            return;
        }

        try {
            app(RhEpiEntregaService::class)->devolver($record, $this->devolverData, $this->devolverObs);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeDevolverModal();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Devolução registrada.')->success()->send();
    }

    public function cancelarEntrega(): void
    {
        if (! ErpAccess::currentCan('rh.epis.delete')) {
            Notification::make()->title('Sem permissão para cancelar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhEpiEntrega::query()->with(['funcionario', 'epi'])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Entrega não encontrada.')->warning()->send();

            return;
        }

        $this->cancelRecordId = (int) $record->getKey();
        $this->cancelMotivo = '';
        $this->cancelNome = trim(($record->epi?->descricao ?? '').' — '.($record->funcionario?->nome ?? ''));
        $this->cancelConfirmOpen = true;
    }

    public function confirmCancelarEntrega(): void
    {
        if (! ErpAccess::currentCan('rh.epis.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->closeCancelConfirm();

            return;
        }

        $record = RhEpiEntrega::query()->find($this->cancelRecordId);
        if ($record) {
            try {
                app(RhEpiEntregaService::class)->cancelar($record, $this->cancelMotivo ?: null);
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->closeCancelConfirm();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Entrega cancelada (histórico preservado).')->success()->send();
    }

    public function closeCancelConfirm(): void
    {
        $this->cancelConfirmOpen = false;
        $this->cancelRecordId = null;
        $this->cancelMotivo = '';
        $this->cancelNome = '';
    }

    public function imprimirComprovanteSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.epis.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('print')) {
            return;
        }

        $url = route('erp.reports.rh-epi-comprovante', [
            'entrega' => $this->highlightedRecordId,
            'auto' => 1,
        ]);
        $this->redirect($url, navigate: false);
    }

    public function anexarComprovanteAssinado(): void
    {
        if (! ErpAccess::currentCan('rh.epis.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        if (! $this->comprovanteUpload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'comprovanteUpload' => 'Selecione o comprovante assinado.',
            ]);
        }

        $record = RhEpiEntrega::query()->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Entrega não encontrada.')->warning()->send();

            return;
        }

        try {
            app(RhAnexoService::class)->store($record, $this->comprovanteUpload, [
                'titulo' => 'COMPROVANTE EPI '.$record->codigo,
                'categoria' => RhAnexo::CAT_EPI,
                'entregue_em' => $record->entregue_em?->toDateString(),
                'observacao' => 'Comprovante assinado anexado',
            ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->comprovanteUpload = null;
        Notification::make()->title('Comprovante anexado.')->success()->send();
    }
}
