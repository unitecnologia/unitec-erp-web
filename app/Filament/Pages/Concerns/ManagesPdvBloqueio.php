<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait ManagesPdvBloqueio
{
    public bool $pdvBloqueado = false;

    public string $pdvUnlockPassword = '';

    public function lockPdv(): void
    {
        // Bloqueio por inatividade removido dos parâmetros da empresa.
    }

    public function confirmUnlockPdv(): void
    {
        $user = Auth::user();

        if (! $user || ! Hash::check($this->pdvUnlockPassword, $user->password)) {
            $this->notifyPdvError('Senha inválida.');

            return;
        }

        $this->pdvBloqueado = false;
        $this->pdvUnlockPassword = '';
        $this->closePdvModal();
        $this->dispatch('erp-pdv-idle-reset');
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelUnlockPdv(): void
    {
        $this->pdvUnlockPassword = '';
    }
}
