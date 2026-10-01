{{-- Mesmo padrão visual do Espelho da NF-e / DANFSe (.erp-nfe-espelho-modal). --}}
@php
    $espelhoVisivel = $this->nfseEspelhoModalOpen && filled($this->nfseEspelhoModalId);
    $espelhoSrc = $espelhoVisivel
        ? route('erp.reports.nfse-espelho', ['nfse' => $this->nfseEspelhoModalId, 'embed' => 1])
        : 'about:blank';
@endphp
<div
    id="erp-nfse-espelho-modal"
    class="erp-lookup-modal erp-nfe-espelho-modal{{ $espelhoVisivel ? ' is-visible' : '' }}"
    style="display: {{ $espelhoVisivel ? 'flex' : 'none' }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-nfse-espelho-title"
    aria-hidden="{{ $espelhoVisivel ? 'false' : 'true' }}"
    data-erp-nfse-espelho-modal
    @if ($espelhoVisivel)
        wire:keydown.escape.window="closeNfseEspelhoModal"
    @endif
>
    <div class="erp-lookup-modal__backdrop" wire:click="closeNfseEspelhoModal"></div>

    <div class="erp-lookup-modal__window erp-nfe-espelho-modal__window">
        <div class="erp-lookup-modal__titlebar erp-nfe-espelho-modal__titlebar">
            <span id="erp-nfse-espelho-title">Espelho da NFS-e — SEM VALIDADE FISCAL</span>
            <button type="button" class="erp-lookup-modal__close" wire:click="closeNfseEspelhoModal" title="Fechar">✕</button>
        </div>

        <div class="erp-nfe-espelho-modal__toolbar">
            <button type="button" wire:click="downloadNfseEspelhoPdf" class="erp-nfe-espelho-modal__btn">
                <span>⬇</span> PDF
            </button>
            <button type="button" wire:click="printNfseEspelhoDocument" class="erp-nfe-espelho-modal__btn">
                <span>🖨</span> Imprimir
            </button>
            <button type="button" wire:click="closeNfseEspelhoModal" class="erp-nfe-espelho-modal__btn erp-nfe-espelho-modal__btn--close">
                <span>✕</span> Sair
            </button>
        </div>

        <div class="erp-nfe-espelho-modal__body">
            <iframe
                class="erp-nfe-espelho-modal__frame"
                src="{{ $espelhoSrc }}"
                title="Espelho da NFS-e"
                data-erp-nfse-espelho-frame
            ></iframe>
        </div>
    </div>
</div>
