<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Support\Erp\Nfce\NfceHistoricoService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait ManagesNfceHistoricoModal
{
    public bool $nfceHistoricoModalOpen = false;

    /** @var array<string, mixed> */
    public array $nfceHistoricoData = [];

    #[On('erp-nfce-open-historico')]
    public function openNfceHistorico(int $id): void
    {
        $record = $this->findNfceNoEscopo($id);
        if ($record === null) {
            Notification::make()
                ->title('NFC-e não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $dados = (new NfceHistoricoService())->montar((int) $record->getKey());
        if ($dados === null) {
            Notification::make()
                ->title('Não foi possível carregar a NFC-e.')
                ->danger()
                ->send();

            return;
        }

        $this->highlightedRecordId = (int) $record->getKey();
        $this->nfceHistoricoData = $dados;
        $this->nfceHistoricoModalOpen = true;
    }

    public function closeNfceHistorico(): void
    {
        $this->nfceHistoricoModalOpen = false;
        $this->nfceHistoricoData = [];
    }
}
