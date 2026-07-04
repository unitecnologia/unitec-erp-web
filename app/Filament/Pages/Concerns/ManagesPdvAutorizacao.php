<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait ManagesPdvAutorizacao
{
    public string $pdvAuthPassword = '';

    public ?string $pdvAuthPendingAction = null;

    protected function pdvAutorizado(): bool
    {
        if (! $this->pdvConfig()->pedirAutorizacaoExcluir()) {
            return true;
        }

        return (bool) session('erp.pdv.autorizado', false);
    }

    protected function clearPdvAutorizacao(): void
    {
        session()->forget('erp.pdv.autorizado');
    }

    protected function requirePdvAutorizacao(string $action): bool
    {
        if ($this->pdvAutorizado()) {
            return true;
        }

        $this->pdvAuthPendingAction = $action;
        $this->pdvAuthPassword = '';
        $this->openPdvModal('autorizacao');
        $this->dispatch('erp-pdv-focus-autorizacao');

        return false;
    }

    public function confirmPdvAutorizacao(): void
    {
        $user = Auth::user();

        if (! $user || ! Hash::check($this->pdvAuthPassword, $user->password)) {
            $this->notifyPdvError('Senha inválida.');

            return;
        }

        session(['erp.pdv.autorizado' => true]);
        $action = $this->pdvAuthPendingAction;
        $this->pdvAuthPassword = '';
        $this->pdvAuthPendingAction = null;
        $this->closePdvModal();

        match ($action) {
            'excluir_item' => $this->openPdvModal('excluir_item'),
            'remover_itens' => $this->openRemoverItensModal(skipAuth: true),
            'estornar_venda' => $this->completeEstornoAutorizacao(),
            default => null,
        };
    }

    protected function completeEstornoAutorizacao(): void
    {
        $vendaId = (int) ($this->consultaVendaEstornoId ?? 0);

        if ($vendaId <= 0) {
            return;
        }

        $this->estornarVenda($vendaId);
        $this->openPdvModal('consulta_venda');
        $this->dispatch('erp-pdv-focus-consulta-venda');
    }

    public function cancelPdvAutorizacao(): void
    {
        $this->pdvAuthPassword = '';
        $this->pdvAuthPendingAction = null;
        $this->consultaVendaEstornoId = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
