<?php

namespace App\Filament\Resources\TefLiquidacaoDestinoResource\Pages\Concerns;

use App\Models\TefLiquidacaoDestino;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Filament\Notifications\Notification;

trait ManagesTefLiquidacaoDestinoDeleteConfirm
{
    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmNome = '';

    public function deleteDestino(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_destino.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $record = TefLiquidacaoDestino::query()
            ->with('terminal:id,nome')
            ->where('empresa_id', $empresaId)
            ->find($recordId);

        if (! $record) {
            Notification::make()->title('Configuração não encontrada.')->warning()->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->getKey();
        $cnpj = filled($record->acquirer_cnpj) ? (string) $record->acquirer_cnpj : 'PADRÃO';
        $this->deleteConfirmNome = trim(($record->terminal?->nome ?? 'Terminal').' / '.$cnpj);
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteDestino(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_destino.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();
            $this->cancelDeleteDestino();

            return;
        }

        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteDestino();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $record = TefLiquidacaoDestino::query()
            ->where('empresa_id', $empresaId)
            ->find($this->deleteConfirmRecordId);

        if ($record) {
            $record->delete();
        }

        $this->cancelDeleteDestino();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Destino excluído.')->success()->send();
    }

    public function cancelDeleteDestino(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmNome = '';
    }
}
