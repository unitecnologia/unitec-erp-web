{{-- Mesmo padrão visual do Espelho da NF-e (.erp-nfe-espelho-modal). --}}
@php
    $danfseVisivel = $this->nfseDanfseModalOpen && filled($this->nfseDanfseModalId);
    $danfseSrc = $danfseVisivel
        ? route('erp.reports.nfse-impressao', ['nfse' => $this->nfseDanfseModalId, 'embed' => 1])
        : 'about:blank';
@endphp
<div
    id="erp-nfse-danfse-modal"
    class="erp-lookup-modal erp-nfe-espelho-modal{{ $danfseVisivel ? ' is-visible' : '' }}"
    style="display: {{ $danfseVisivel ? 'flex' : 'none' }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-nfse-danfse-title"
    aria-hidden="{{ $danfseVisivel ? 'false' : 'true' }}"
    data-erp-nfse-danfse-modal
    @if ($danfseVisivel)
        wire:keydown.escape.window="closeNfseDanfseModal"
    @endif
>
    <div class="erp-lookup-modal__backdrop" wire:click="closeNfseDanfseModal"></div>

    <div class="erp-lookup-modal__window erp-nfe-espelho-modal__window">
        <div class="erp-lookup-modal__titlebar erp-nfe-espelho-modal__titlebar">
            <span id="erp-nfse-danfse-title">DANFSe — NFS-e</span>
            <button type="button" class="erp-lookup-modal__close" wire:click="closeNfseDanfseModal" title="Fechar">✕</button>
        </div>

        <div class="erp-nfe-espelho-modal__toolbar">
            <button type="button" wire:click="downloadNfseDanfsePdf" class="erp-nfe-espelho-modal__btn">
                <span>⬇</span> PDF
            </button>
            <button type="button" wire:click="printNfseDanfseDocument" class="erp-nfe-espelho-modal__btn">
                <span>🖨</span> Imprimir
            </button>
            <button type="button" wire:click="abrirNfseEnviarFromDanfse" class="erp-nfe-espelho-modal__btn">
                <span>✉</span> E-mail
            </button>
            <button type="button" wire:click="closeNfseDanfseModal" class="erp-nfe-espelho-modal__btn erp-nfe-espelho-modal__btn--close">
                <span>✕</span> Sair
            </button>
        </div>

        <div class="erp-nfe-espelho-modal__body">
            <iframe
                class="erp-nfe-espelho-modal__frame"
                src="{{ $danfseSrc }}"
                title="DANFSe da NFS-e"
                data-erp-nfse-danfse-frame
            ></iframe>
        </div>
    </div>
</div>
