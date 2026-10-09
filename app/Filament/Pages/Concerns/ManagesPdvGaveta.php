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

        if (! $this->pdvConfig()->gavetaDisponivel()) {
            $this->notifyPdvError(
                'Gaveta indisponível.',
                'Marque "Usa Gaveta" no Terminal e configure uma impressora de bobina (ESC/POS) com nome Windows.',
            );

            return;
        }

        $this->dispatch('erp-pdv-gaveta', printer: $this->pdvConfig()->impressoraNome(), manual: true);

        Notification::make()
            ->title('Abrir gaveta')
            ->body('Comando enviado à impressora do terminal.')
            ->success()
            ->send();
    }

    /** Após receber em dinheiro: só abre se o terminal usa gaveta e tem impressora compatível. */
    protected function abrirGavetaAutomatica(): void
    {
        if (! $this->pdvConfig()->gavetaDisponivel()) {
            return;
        }

        $this->dispatch('erp-pdv-gaveta', printer: $this->pdvConfig()->impressoraNome(), manual: false);
    }
}
