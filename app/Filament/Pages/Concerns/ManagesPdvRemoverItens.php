<?php

namespace App\Filament\Pages\Concerns;

use Filament\Notifications\Notification;

trait ManagesPdvRemoverItens
{
    public string $removerItensSearch = '';

    public ?int $removerItensIndex = null;

    public bool $removerItensConfirmando = false;

    public function openRemoverItensModal(bool $skipAuth = false): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->cupomTemItens()) {
            $this->notifyPdvError('Nenhum item no cupom.');

            return;
        }

        if (! $skipAuth && ! $this->requirePdvAutorizacao('remover_itens')) {
            return;
        }

        $this->resetRemoverItensState();
        $this->openPdvModal('remover_itens');
        $this->dispatch('erp-pdv-focus-remover-itens');
    }

    public function handleRemoverItensSearchEnter(?string $termo = null): void
    {
        if ($this->removerItensConfirmando) {
            return;
        }

        $termo = mb_strtoupper(trim((string) ($termo ?? $this->removerItensSearch)), 'UTF-8');

        if ($termo === '') {
            return;
        }

        $index = $this->findCupomIndexForRemoverTermo($termo);

        if ($index === null) {
            $this->removerItensSearch = '';
            $this->dispatch('erp-pdv-erro-beep');
            $this->notifyPdvError('Produto não encontrado no cupom.');
            $this->dispatch('erp-pdv-focus-remover-itens');

            return;
        }

        $this->removerItensIndex = $index;
        $this->removerItensConfirmando = true;
        $this->removerItensSearch = '';
        $this->dispatch('erp-pdv-focus-remover-itens-confirm');
    }

    public function confirmRemoverItens(): void
    {
        if (! $this->removerItensConfirmando || $this->removerItensIndex === null) {
            return;
        }

        $index = $this->removerItensIndex;

        if (! isset($this->cupomItens[$index])) {
            $this->voltarRemoverItensScan();

            return;
        }

        $item = $this->cupomItens[$index];
        $qtdAtual = (float) ($item['quantidade'] ?? 0);
        $qtdRemover = 1.0;

        if ($qtdAtual <= 0) {
            unset($this->cupomItens[$index]);
            $this->cupomItens = array_values($this->cupomItens);
        } elseif ($qtdRemover >= $qtdAtual) {
            unset($this->cupomItens[$index]);
            $this->cupomItens = array_values($this->cupomItens);
        } else {
            $preco = (float) ($item['preco'] ?? 0);
            $novaQtd = round($qtdAtual - $qtdRemover, 3);
            $this->cupomItens[$index]['quantidade'] = $novaQtd;
            $this->cupomItens[$index]['total'] = round($novaQtd * $preco, 2);
        }

        if ($this->selectedCupomIndex !== null && ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            $this->selectedCupomIndex = null;
            $this->pdvMostrarDetalheItem = false;
        }

        $productId = (int) ($item['product_id'] ?? 0);

        if ($productId > 0) {
            $this->recheckAtacadoPrices($productId);
        }

        $this->persistCupomToSession();
        $this->dispatch('erp-pdv-beep');

        $descricao = (string) ($item['descricao'] ?? 'item');

        $this->closePdvModal();
        $this->clearPdvAutorizacao();
        $this->resetRemoverItensState();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title('1 unidade removida.')
            ->body($descricao)
            ->success()
            ->send();
    }

    public function cancelRemoverItensConfirm(): void
    {
        $this->voltarRemoverItensScan();
    }

    public function cancelRemoverItens(): void
    {
        if ($this->removerItensConfirmando) {
            $this->voltarRemoverItensScan();

            return;
        }

        $this->closePdvModal();
        $this->clearPdvAutorizacao();
        $this->resetRemoverItensState();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function getRemoverItensItemProperty(): ?array
    {
        if ($this->removerItensIndex === null || ! isset($this->cupomItens[$this->removerItensIndex])) {
            return null;
        }

        return $this->cupomItens[$this->removerItensIndex];
    }

    protected function voltarRemoverItensScan(): void
    {
        $this->removerItensConfirmando = false;
        $this->removerItensIndex = null;
        $this->removerItensSearch = '';
        $this->dispatch('erp-pdv-focus-remover-itens');
    }

    protected function resetRemoverItensState(): void
    {
        $this->removerItensSearch = '';
        $this->removerItensIndex = null;
        $this->removerItensConfirmando = false;
    }

    /**
     * Localiza no cupom pelo código interno ou código de barras (última ocorrência).
     */
    protected function findCupomIndexForRemoverTermo(string $termo): ?int
    {
        $found = null;

        foreach ($this->cupomItens as $index => $item) {
            $codigo = mb_strtoupper(trim((string) ($item['codigo'] ?? '')), 'UTF-8');
            $barras = mb_strtoupper(trim((string) ($item['codigo_barras'] ?? '')), 'UTF-8');

            if ($termo === $codigo || ($barras !== '' && $termo === $barras)) {
                $found = (int) $index;
            }
        }

        return $found;
    }
}
