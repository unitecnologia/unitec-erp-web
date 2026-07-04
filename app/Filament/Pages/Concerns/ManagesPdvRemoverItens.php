<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesPdvRemoverItens
{
    public string $removerItensQtd = '1';

    public function openRemoverItensModal(bool $skipAuth = false): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $item = $this->cupomItemSelecionado;

        if (! $item) {
            $this->notifyPdvError('Selecione um item do cupom.');

            return;
        }

        if (! $skipAuth && ! $this->requirePdvAutorizacao('remover_itens')) {
            return;
        }

        $this->removerItensQtd = ErpMoney::formatBr($item['quantidade'] ?? 1, 3);
        $this->openPdvModal('remover_itens');
        $this->dispatch('erp-pdv-focus-remover-itens');
    }

    public function confirmRemoverItens(): void
    {
        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            $this->closePdvModal();

            return;
        }

        $index = $this->selectedCupomIndex;
        $item = $this->cupomItens[$index];
        $qtdRemover = ErpMoney::parseBr($this->removerItensQtd, 3);
        $qtdAtual = (float) ($item['quantidade'] ?? 0);

        if ($qtdRemover <= 0) {
            $this->notifyPdvError('Quantidade inválida.');

            return;
        }

        if ($qtdRemover > $qtdAtual) {
            $this->notifyPdvError('Quantidade maior que o item.');

            return;
        }

        if ($qtdRemover >= $qtdAtual) {
            unset($this->cupomItens[$index]);
            $this->cupomItens = array_values($this->cupomItens);
            $this->selectedCupomIndex = null;
        } else {
            $preco = (float) ($item['preco'] ?? 0);
            $novaQtd = round($qtdAtual - $qtdRemover, 3);
            $this->cupomItens[$index]['quantidade'] = $novaQtd;
            $this->cupomItens[$index]['total'] = round($novaQtd * $preco, 2);
        }

        $productId = (int) ($item['product_id'] ?? 0);

        if ($productId > 0) {
            $this->recheckAtacadoPrices($productId);
        }

        $this->persistCupomToSession();
        $this->closePdvModal();
        $this->clearPdvAutorizacao();

        Notification::make()
            ->title('Item atualizado.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelRemoverItens(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
