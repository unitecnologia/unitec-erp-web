<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Vendedor;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvVendedor
{
    /** @var array<int, array<string, mixed>> */
    public array $vendedorResults = [];

    public ?int $selectedVendedorIndex = null;

    public string $vendedorSearch = '';

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

    protected function notificarOperadorObrigatorio(): void
    {
        Notification::make()
            ->title('Operador não vinculado ao usuário.')
            ->body(
                "Não é possível abrir o caixa sem vínculo.\n".
                "1) Cadastre o Operador (Pessoas → Operadores)\n".
                "2) Cadastre/edite o Usuário e vincule esse Operador\n".
                "3) Confira empresa, PDV liberado e caixa\n".
                'Depois tente abrir o PDV novamente.'
            )
            ->danger()
            ->persistent()
            ->send();
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

    public function openVendedorModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->pdvConfig()->exibirF3Vendedor()) {
            $this->notifyPdvError('Função vendedor (F3) desabilitada nos parâmetros da empresa.');

            return;
        }

        $this->vendedorSearch = $this->vendedor;
        $this->refreshVendedorResults();
        $this->openPdvModal('vendedor');
        $this->dispatch('erp-pdv-focus-vendedor');
    }

    public function refreshVendedorResults(): void
    {
        $term = trim($this->vendedorSearch);
        $like = '%' . $term . '%';
        $terminalId = $this->resolveTerminalIdAtual();

        $query = Vendedor::query()
            ->with('terminais')
            ->where('ativo', true);

        if ($term !== '') {
            $query->where(function ($q) use ($like): void {
                $q->where('nome', 'like', $like)
                    ->orWhere('codigo', 'like', $like);
            });
        }

        // Só operadores liberados neste PDV (sem PDVs marcados = liberado em todos).
        $this->vendedorResults = $query
            ->orderBy('nome')
            ->limit(200)
            ->get()
            ->filter(fn (Vendedor $v): bool => $v->podeUsarTerminal($terminalId))
            ->take(100)
            ->map(fn (Vendedor $v): array => [
                'vendedor_id' => $v->id,
                'codigo' => $v->codigo,
                'nome' => mb_strtoupper($v->nome, 'UTF-8'),
            ])
            ->values()
            ->all();

        $this->selectedVendedorIndex = $this->vendedorResults === [] ? null : 0;
    }

    public function updatedVendedorSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->vendedorSearch !== $upper) {
            $this->vendedorSearch = $upper;
        }

        $this->refreshVendedorResults();
    }

    public function selectVendedorResult(int $index): void
    {
        if (! isset($this->vendedorResults[$index])) {
            return;
        }

        $this->selectedVendedorIndex = $index;
    }

    public function moveVendedorSelection(int $delta): void
    {
        if ($this->vendedorResults === []) {
            return;
        }

        $count = count($this->vendedorResults);
        $index = ($this->selectedVendedorIndex ?? 0) + $delta;
        $this->selectedVendedorIndex = max(0, min($count - 1, $index));
    }

    public function confirmVendedor(): void
    {
        $index = $this->selectedVendedorIndex;

        if ($index === null || ! isset($this->vendedorResults[$index])) {
            Notification::make()
                ->title('Selecione um Operador.')
                ->body('Não é permitido operar sem Operador cadastrado.')
                ->warning()
                ->send();

            return;
        }

        $row = $this->vendedorResults[$index];
        $vendedorId = (int) $row['vendedor_id'];
        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());
        $terminalId = $terminal?->id ? (int) $terminal->id : null;
        $terminalNome = trim((string) ($terminal?->nome ?? '')) ?: 'este PDV';

        $vendedor = Vendedor::query()
            ->with('terminais')
            ->whereKey($vendedorId)
            ->where('ativo', true)
            ->first();

        if (! $vendedor || ! $vendedor->podeUsarTerminal($terminalId)) {
            Notification::make()
                ->title('Operador não liberado neste PDV.')
                ->body(
                    mb_strtoupper((string) ($row['nome'] ?? 'Operador'), 'UTF-8').
                    ' não está autorizado a operar em "'.$terminalNome.'".'.
                    "\nLibere o PDV em Pessoas → Operadores → PDVs liberados."
                )
                ->danger()
                ->send();

            return;
        }

        $this->vendedorId = $vendedorId;
        $this->vendedor = (string) $row['nome'];

        $this->persistVendedorToSession();
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function resolveTerminalIdAtual(): ?int
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());

        return $terminal?->id ? (int) $terminal->id : null;
    }
}
