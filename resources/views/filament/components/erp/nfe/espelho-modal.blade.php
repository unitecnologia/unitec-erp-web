{{-- Sempre no DOM: Livewire morph falha ao inserir @if após saveNfe() no lançamento. --}}
@php
    $espelhoVisivel = $this->nfeEspelhoModalOpen && filled($this->nfeEspelhoNfeId);
    $espelhoSrc = $espelhoVisivel
        ? route('erp.reports.nfe-espelho', ['nfe' => $this->nfeEspelhoNfeId, 'embed' => 1])
        : 'about:blank';
@endphp
<div
    id="erp-nfe-espelho-modal"
    class="erp-lookup-modal erp-nfe-espelho-modal{{ $espelhoVisivel ? ' is-visible' : '' }}"
    style="display: {{ $espelhoVisivel ? 'flex' : 'none' }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-nfe-espelho-title"
    aria-hidden="{{ $espelhoVisivel ? 'false' : 'true' }}"
    data-erp-nfe-espelho-modal
    @if ($espelhoVisivel)
        wire:keydown.escape.window="closeNfeEspelho"
    @endif
>
    <div class="erp-lookup-modal__backdrop" wire:click="closeNfeEspelho"></div>

    <div class="erp-lookup-modal__window erp-nfe-espelho-modal__window">
        <div class="erp-lookup-modal__titlebar erp-nfe-espelho-modal__titlebar">
            <span id="erp-nfe-espelho-title">Espelho da NF-e — SEM VALIDADE FISCAL</span>
            <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeEspelho" title="Fechar">✕</button>
        </div>

        <div class="erp-nfe-espelho-modal__toolbar">
            <button type="button" wire:click="openNfeEspelhoWhatsAppCliente" class="erp-nfe-espelho-modal__btn">
                <span>📱</span> WhatsApp Cliente
            </button>
            <button type="button" wire:click="openNfeEspelhoWhatsAppFornecedor" class="erp-nfe-espelho-modal__btn">
                <span>📱</span> WhatsApp Fornecedor
            </button>
            <button type="button" wire:click="openNfeEspelhoEmailModal" class="erp-nfe-espelho-modal__btn">
                <span>✉</span> E-mail
            </button>
            <button type="button" wire:click="printNfeEspelhoDocument" class="erp-nfe-espelho-modal__btn">
                <span>🖨</span> Imprimir
            </button>
            <button type="button" wire:click="closeNfeEspelho" class="erp-nfe-espelho-modal__btn erp-nfe-espelho-modal__btn--close">
                <span>✕</span> Sair
            </button>
        </div>

        <div class="erp-nfe-espelho-modal__body">
            <iframe
                class="erp-nfe-espelho-modal__frame"
                src="{{ $espelhoSrc }}"
                title="Espelho da NF-e"
            ></iframe>
        </div>
    </div>
</div>
