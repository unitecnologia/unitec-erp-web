<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Vendedor;

trait ManagesPdvVendedor
{
    /** @var array<int, array<string, mixed>> */
    public array $vendedorResults = [];

    public ?int $selectedVendedorIndex = null;

    public string $vendedorSearch = '';

    public ?int $vendedorId = null;

    protected function loadVendedorFromSession(): void
    {
        $this->vendedorId = session('erp.pdv.vendedor_id');
        $this->vendedor = (string) session('erp.pdv.vendedor', 'LOJA');
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

        $query = Vendedor::query()->where('ativo', true);

        if ($term !== '') {
            $query->where(function ($q) use ($like): void {
                $q->where('nome', 'like', $like)
                    ->orWhere('codigo', 'like', $like);
            });
        }

        $this->vendedorResults = $query
            ->orderBy('nome')
            ->limit(100)
            ->get()
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

        if ($index !== null && isset($this->vendedorResults[$index])) {
            $row = $this->vendedorResults[$index];
            $this->vendedorId = $row['vendedor_id'];
            $this->vendedor = $row['nome'];
        } else {
            $nome = trim($this->vendedorSearch);
            $this->vendedor = $nome !== '' ? $nome : 'LOJA';
            $this->vendedorId = null;
        }

        $this->persistVendedorToSession();
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
