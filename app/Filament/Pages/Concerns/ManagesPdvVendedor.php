<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Vendedor;
use Illuminate\Support\Facades\Auth;

/**
 * Operador da venda: sempre o vinculado ao usuário logado (RH → Operador).
 * Troca manual de vendedor no PDV foi removida (F3 virou Acesso Rápido).
 */
trait ManagesPdvVendedor
{
    public ?int $vendedorId = null;

    protected function loadVendedorFromSession(): void
    {
        // Sem Operador no usuário logado não opera PDV (sem fallback LOJA).
        if (! $this->garantirOperadorDoUsuarioLogado(notify: false)) {
            $this->vendedorId = null;
            $this->vendedor = '';
            $this->persistVendedorToSession();

            return;
        }

        $sessionId = session('erp.pdv.vendedor_id');

        if ($sessionId) {
            $vendedor = Vendedor::query()
                ->whereKey((int) $sessionId)
                ->where('ativo', true)
                ->first();

            if ($vendedor) {
                $this->vendedorId = (int) $vendedor->id;
                $this->vendedor = mb_strtoupper((string) $vendedor->nome, 'UTF-8');
                $this->persistVendedorToSession();

                return;
            }
        }

        $this->aplicarVendedorDoUsuarioLogado();
    }

    /**
     * Garante Operador ativo vinculado ao usuário logado.
     * Sem vínculo: trava e orienta o cadastro.
     */
    protected function garantirOperadorDoUsuarioLogado(bool $notify = true): bool
    {
        $user = Auth::user();

        if (! $user) {
            if ($notify) {
                $this->notificarOperadorObrigatorio();
            }

            return false;
        }

        $vendedor = $user->relationLoaded('vendedor')
            ? $user->vendedor
            : $user->vendedor()->first();

        if (! $vendedor || ! $vendedor->ativo) {
            $this->vendedorId = null;
            $this->vendedor = '';
            $this->persistVendedorToSession();

            if ($notify) {
                $this->notificarOperadorObrigatorio();
            }

            return false;
        }

        return true;
    }

    protected function notificarOperadorObrigatorio(bool $voltarDashboard = false): void
    {
        $this->openPdvAcessoNegado(
            'Operador não vinculado ao usuário.',
            [
                'Não é possível abrir o caixa sem vínculo.',
                '1. Em <strong>RH → Funcionários</strong>, marque a aba <strong>Operador</strong> e vincule o usuário.',
                '2. Confira empresas/caixas em <strong>Permissões / Usuários</strong>.',
                '3. Confira PDV liberado e caixa.',
            ],
            $voltarDashboard,
            'Depois tente abrir o PDV novamente.',
        );
    }

    /**
     * Usa o Operador vinculado ao usuário logado (users.vendedor_id).
     */
    protected function aplicarVendedorDoUsuarioLogado(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $vendedor = $user->relationLoaded('vendedor')
            ? $user->vendedor
            : $user->vendedor()->first();

        if (! $vendedor || ! $vendedor->ativo) {
            return false;
        }

        $this->vendedorId = (int) $vendedor->id;
        $this->vendedor = mb_strtoupper((string) $vendedor->nome, 'UTF-8');
        $this->persistVendedorToSession();

        return true;
    }

    protected function persistVendedorToSession(): void
    {
        session([
            'erp.pdv.vendedor_id' => $this->vendedorId,
            'erp.pdv.vendedor' => $this->vendedor,
        ]);
    }
}
