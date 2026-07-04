<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait ManagesPdvBloqueio
{
    public bool $pdvBloqueado = false;

    public string $pdvUnlockPassword = '';

    public function getPdvTempoBloqueioMinProperty(): ?int
    {
        if (! $this->pdvConfig()->bloquearInatividade()) {
            return null;
        }

        $min = $this->pdvConfig()->tempoBloqueioPdvMin();

        return $min > 0 ? $min : null;
    }

    public function lockPdv(): void
    {
        if (! $this->caixaAberto
            || ! $this->pdvConfig()->bloquearInatividade()
            || $this->pdvConfig()->tempoBloqueioPdvMin() <= 0) {
            return;
        }

        if ($this->pdvBloqueado) {
            return;
        }

        $this->pdvBloqueado = true;
        $this->pdvUnlockPassword = '';
        $this->openPdvModal('bloqueio');
        $this->dispatch('erp-pdv-focus-bloqueio');
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
