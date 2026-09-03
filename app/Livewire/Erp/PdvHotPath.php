<?php

namespace App\Livewire\Erp;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvCaixaRapidoBipService;
use App\Support\Erp\Pdv\PdvConfig;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Hot path do bip: cupom + flash + scanner Livewire leve (fora do Filament Page).
 */
class PdvHotPath extends Component
{
    /** @var list<array<string, mixed>> */
    public array $cupomItens = [];

    public bool $caixaAberto = false;

    public bool $pdvCaixaRapido = true;

    public ?string $pdvFlashQtd = null;

    public ?string $pdvFlashPreco = null;

    public ?string $pdvFlashTotal = null;

    public ?string $pdvPreviewFotoUrl = null;

    public ?string $pdvPreviewProductName = null;

    public ?string $produtoNaoEncontradoCodigo = null;

    public int $produtoNaoEncontradoCount = 0;

    public function mount(bool $caixaAberto = false): void
    {
        $this->caixaAberto = $caixaAberto;
        $this->pdvCaixaRapido = PdvConfig::make()->caixaRapido();
        $this->loadCupomFromSession();
    }

    #[On('erp-pdv-hot-sync-caixa')]
    public function syncCaixa(bool $aberto): void
    {
        $this->caixaAberto = $aberto;
    }

    #[On('erp-pdv-hot-reload-cupom')]
    public function reloadCupomFromSession(): void
    {
        $this->loadCupomFromSession();
        $this->pdvFlashQtd = null;
        $this->pdvFlashPreco = null;
        $this->pdvFlashTotal = null;
    }

    public function handlePdvSearchEnter(?string $codigo = null): void
    {
        $code = is_string($codigo) ? $codigo : '';

        // Fora do Caixa Rápido: delega ao PDV page (mesmas regras/modais).
        if (! $this->pdvCaixaRapido) {
            $this->delegateToParent($code);

            return;
        }

        $result = PdvCaixaRapidoBipService::make()->handle(
            $code,
            $this->cupomItens,
            $this->caixaAberto,
        );

        if ($result['status'] === 'caixa_fechado') {
            $this->dispatch('erp-pdv-hot-delegate', codigo: $code);

            return;
        }

        if ($result['status'] === 'empty') {
            $this->dispatch('erp-pdv-hot-empty-enter');

            return;
        }

        if ($result['status'] === 'delegate') {
            $this->delegateToParent($code);

            return;
        }

        if ($result['status'] === 'not_found') {
            $termo = mb_strtoupper(trim($code), 'UTF-8');
            $this->produtoNaoEncontradoCodigo = $termo !== '' ? $termo : '—';
            $this->produtoNaoEncontradoCount++;
            $this->dispatch('erp-pdv-erro-beep');
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        // added
        $this->cupomItens = $result['cupom'];
        $this->pdvFlashQtd = $result['flash']['qtd'];
        $this->pdvFlashPreco = $result['flash']['preco'];
        $this->pdvFlashTotal = $result['flash']['total'];
        $this->pdvPreviewProductName = $result['preview_name'];
        $this->pdvPreviewFotoUrl = $result['preview_foto'];
        $this->produtoNaoEncontradoCodigo = null;

        session(['erp.pdv.cupom' => $this->cupomItens]);

        $this->dispatch('erp-pdv-item-added');
        $this->dispatch('erp-pdv-beep');
        if (filled($result['produto_nome'])) {
            $this->dispatch('erp-pdv-produto-confirmado', nome: $result['produto_nome']);
        }
        $this->dispatch('erp-pdv-focus-search');

        $this->js(<<<'JS'
            window.__erpPdvForceSearchFocusUntil = Date.now() + 4500;
            window.dispatchEvent(new CustomEvent('erp-pdv-refocus-search'));
        JS);
    }

    public function fecharProdutoNaoEncontrado(): void
    {
        $this->produtoNaoEncontradoCodigo = null;
        $this->produtoNaoEncontradoCount = 0;
        $this->dispatch('erp-pdv-focus-search');
    }

    public function selectCupomItem(int $index): void
    {
        // Seleção/desconto ficam no page; só avisa o pai.
        $this->dispatch('erp-pdv-hot-select-cupom', index: $index);
    }

    public function getCupomTotalProperty(): string
    {
        $total = collect($this->cupomItens)->sum(fn (array $item): float => (float) ($item['total'] ?? 0));

        return ErpMoney::formatBr((float) $total);
    }

    public function formatCupomQuantidade(float $quantidade): string
    {
        return fmod($quantidade, 1.0) === 0.0
            ? (string) (int) $quantidade
            : number_format($quantidade, 3, ',', '');
    }

    public function render(): View
    {
        return view('livewire.erp.pdv-hot-path');
    }

    protected function loadCupomFromSession(): void
    {
        $stored = session('erp.pdv.cupom', []);
        $this->cupomItens = is_array($stored) ? $stored : [];
    }

    protected function delegateToParent(string $codigo): void
    {
        $this->dispatch('erp-pdv-hot-delegate', codigo: $codigo);
    }
}
