<?php

namespace App\Filament\Resources\RhTreinamentoResource\Pages\Concerns;

use App\Models\RhAnexo;
use App\Models\RhTreinamentoParticipante;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhTreinamentoService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhTreinamentoMovimentos
{
    use WithFileUploads;

    public bool $cancelConfirmOpen = false;

    public ?int $cancelRecordId = null;

    public string $cancelMotivo = '';

    public string $cancelNome = '';

    public ?TemporaryUploadedFile $certificadoUpload = null;

    public function updatedCertificadoUpload(): void
    {
        if ($this->certificadoUpload instanceof TemporaryUploadedFile) {
            $this->anexarCertificado();
        }
    }

    public function cancelarParticipante(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.delete')) {
            Notification::make()->title('Sem permissão para cancelar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhTreinamentoParticipante::query()
            ->with(['funcionario', 'realizacao.treinamento'])
            ->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Participante não encontrado.')->warning()->send();

            return;
        }

        $this->cancelRecordId = (int) $record->getKey();
        $this->cancelMotivo = '';
        $this->cancelNome = trim(($record->realizacao?->treinamento?->nome ?? '').' — '.($record->funcionario?->nome ?? ''));
        $this->cancelConfirmOpen = true;
    }

    public function confirmCancelarParticipante(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->closeCancelConfirm();

            return;
        }

        $record = RhTreinamentoParticipante::query()->find($this->cancelRecordId);
        if ($record) {
            try {
                app(RhTreinamentoService::class)->cancelarParticipante($record, $this->cancelMotivo ?: null);
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->closeCancelConfirm();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Participante cancelado (histórico preservado).')->success()->send();
    }

    public function closeCancelConfirm(): void
    {
        $this->cancelConfirmOpen = false;
        $this->cancelRecordId = null;
        $this->cancelMotivo = '';
        $this->cancelNome = '';
    }

    public function anexarCertificado(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        if (! $this->certificadoUpload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'certificadoUpload' => 'Selecione o certificado.',
            ]);
        }

        $record = RhTreinamentoParticipante::query()->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Participante não encontrado.')->warning()->send();

            return;
        }

        try {
            app(RhAnexoService::class)->store($record, $this->certificadoUpload, [
                'titulo' => 'CERTIFICADO TREINAMENTO '.$record->codigo,
                'categoria' => RhAnexo::CAT_TREINAMENTO,
                'valido_ate' => $record->vencimento_em?->toDateString(),
                'observacao' => 'Certificado anexado',
            ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->certificadoUpload = null;
        Notification::make()->title('Certificado anexado.')->success()->send();
    }
}
