<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesPdvReimprimir
{
    public string $reimprimirSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $reimprimirResults = [];

    public ?int $selectedReimprimirIndex = null;

    public function openReimprimirModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->reimprimirSearch = '';
        $this->refreshReimprimirResults();
        $this->openPdvModal('reimprimir');
        $this->dispatch('erp-pdv-focus-reimprimir');
    }

    public function updatedReimprimirSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->reimprimirSearch !== $upper) {
            $this->reimprimirSearch = $upper;
        }

        $this->refreshReimprimirResults();
    }

    public function refreshReimprimirResults(): void
    {
        if (! $this->caixaSessaoId) {
            $this->reimprimirResults = [];
            $this->selectedReimprimirIndex = null;

            return;
        }

        $term = trim($this->reimprimirSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('situacao', '!=', 'C')
            ->orderByDesc('numero');

        if ($like) {
            $query->where(function ($q) use ($like, $term): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('vendedor_nome', 'like', $like);

                if (is_numeric($term)) {
                    $q->orWhere('numero', (int) $term);
                }
            });
        }

        $this->reimprimirResults = $query
            ->limit(50)
            ->get()
            ->map(fn (PdvVenda $venda): array => [
                'venda_id' => $venda->id,
                'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
                'total' => ErpMoney::formatBr($venda->total),
                'forma' => $venda->forma_pagamento ?? '—',
                'vendedor' => mb_strtoupper($venda->vendedor_nome ?? '—', 'UTF-8'),
            ])
            ->values()
            ->all();

        $this->selectedReimprimirIndex = $this->reimprimirResults === [] ? null : 0;
    }

    public function selectReimprimirRow(int $index): void
    {
        if (isset($this->reimprimirResults[$index])) {
            $this->selectedReimprimirIndex = $index;
        }
    }

    public function moveReimprimirSelection(int $delta): void
    {
        if ($this->reimprimirResults === []) {
            return;
        }

        $count = count($this->reimprimirResults);
        $index = ($this->selectedReimprimirIndex ?? 0) + $delta;
        $this->selectedReimprimirIndex = max(0, min($count - 1, $index));
    }

    public function confirmReimprimir(): void
    {
        $index = $this->selectedReimprimirIndex;

        if ($index === null || ! isset($this->reimprimirResults[$index])) {
            $this->notifyPdvError('Selecione uma venda.');

            return;
        }

        $vendaId = (int) ($this->reimprimirResults[$index]['venda_id'] ?? 0);
        $copias = $this->pdvConfig()->pedidoDuasVias() ? 2 : 1;

        $this->imprimirCupomPosVenda($vendaId, $copias);
        $this->closePdvModal();

        Notification::make()
            ->title('Impressão enviada.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelReimprimir(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
