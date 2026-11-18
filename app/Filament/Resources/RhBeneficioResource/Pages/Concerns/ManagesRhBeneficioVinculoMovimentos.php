<?php

namespace App\Filament\Resources\RhBeneficioResource\Pages\Concerns;

use App\Models\RhBeneficioVinculo;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhBeneficioService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesRhBeneficioVinculoMovimentos
{
    public bool $cancelConfirmOpen = false;

    public ?int $cancelRecordId = null;

    public string $cancelMotivo = '';

    public string $cancelNome = '';

    public bool $encerrarModalOpen = false;

    public ?int $encerrarRecordId = null;

    public string $encerrarData = '';

    public string $encerrarMotivo = '';

    public function cancelarVinculo(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.delete')) {
            Notification::make()->title('Sem permissão para cancelar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhBeneficioVinculo::query()->with(['funcionario', 'beneficio'])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Vínculo não encontrado.')->warning()->send();

            return;
        }

        $this->cancelRecordId = (int) $record->getKey();
        $this->cancelMotivo = '';
        $this->cancelNome = trim(($record->beneficio?->descricao ?? '').' — '.($record->funcionario?->nome ?? ''));
        $this->cancelConfirmOpen = true;
    }

    public function confirmCancelarVinculo(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->closeCancelConfirm();

            return;
        }

        $record = RhBeneficioVinculo::query()->find($this->cancelRecordId);
        if ($record) {
            try {
                app(RhBeneficioService::class)->cancelarVinculo($record, $this->cancelMotivo ?: null);
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return;
            }
        }

        $this->closeCancelConfirm();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Vínculo cancelado (histórico preservado).')->success()->send();
    }

    public function closeCancelConfirm(): void
    {
        $this->cancelConfirmOpen = false;
        $this->cancelRecordId = null;
        $this->cancelMotivo = '';
        $this->cancelNome = '';
    }

    public function openEncerrarModal(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.update')) {
            Notification::make()->title('Sem permissão para encerrar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhBeneficioVinculo::query()->find($this->highlightedRecordId);
        if (! $record || $record->situacao !== RhBeneficioVinculo::SIT_ATIVO) {
            Notification::make()->title('Selecione um vínculo ativo.')->warning()->send();

            return;
        }

        $this->encerrarRecordId = (int) $record->getKey();
        $this->encerrarData = now('America/Sao_Paulo')->toDateString();
        $this->encerrarMotivo = '';
        $this->encerrarModalOpen = true;
    }

    public function closeEncerrarModal(): void
    {
        $this->encerrarModalOpen = false;
        $this->encerrarRecordId = null;
        $this->encerrarData = '';
        $this->encerrarMotivo = '';
    }

    public function confirmEncerrarVinculo(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->validate([
            'encerrarData' => ['required', 'date'],
            'encerrarMotivo' => ['nullable', 'string', 'max:255'],
        ], [], [
            'encerrarData' => 'data de encerramento',
        ]);

        $record = RhBeneficioVinculo::query()->find($this->encerrarRecordId);
        if (! $record) {
            $this->closeEncerrarModal();

            return;
        }

        try {
            app(RhBeneficioService::class)->encerrar($record, $this->encerrarData, $this->encerrarMotivo ?: null);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeEncerrarModal();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Vínculo encerrado.')->success()->send();
    }
}
