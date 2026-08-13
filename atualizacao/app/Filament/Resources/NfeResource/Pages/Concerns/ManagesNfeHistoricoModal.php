<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Support\Erp\Nfe\NfeHistoricoService;
use Filament\Notifications\Notification;

trait ManagesNfeHistoricoModal
{
    public bool $nfeHistoricoModalOpen = false;

    /** @var array<string, mixed> */
    public array $nfeHistoricoData = [];

    public function openNfeHistorico(int $nfeId): void
    {
        $dados = (new NfeHistoricoService())->montar($nfeId);

        if ($dados === null) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $this->nfeHistoricoData = $dados;
        $this->nfeHistoricoModalOpen = true;
        $this->dispatch('erp-nfe-focus-historico-modal');
    }

    public function closeNfeHistorico(): void
    {
        $this->nfeHistoricoModalOpen = false;
        $this->nfeHistoricoData = [];
    }
}
