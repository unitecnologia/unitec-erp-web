<?php

namespace App\Filament\Pages\Concerns;

use Filament\Notifications\Notification;

trait ManagesPdvGaveta
{
    public function abrirGaveta(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->dispatch('erp-pdv-gaveta');

        Notification::make()
            ->title('Abrir gaveta')
            ->body('Comando enviado. Integração com gaveta física depende do equipamento local.')
            ->success()
            ->send();
    }
}
