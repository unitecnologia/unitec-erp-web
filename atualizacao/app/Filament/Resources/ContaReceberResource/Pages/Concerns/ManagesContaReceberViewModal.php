<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Support\Erp\Financeiro\ContaReceberDetalheService;
use Filament\Notifications\Notification;

trait ManagesContaReceberViewModal
{
    public bool $viewModalOpen = false;

    /** @var array<string, mixed> */
    public array $viewModalData = [];

    public function openContaReceberView(int $contaId): void
    {
        $dados = (new ContaReceberDetalheService())->montar($contaId);

        if ($dados === null) {
            Notification::make()
                ->title('Conta a receber não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $this->viewModalData = $dados;
        $this->viewModalOpen = true;
    }

    public function closeContaReceberView(): void
    {
        $this->viewModalOpen = false;
        $this->viewModalData = [];
    }
}
