<div class="erp-nfe-produto-lookup-panel">
    <div class="erp-nfe-produto-lookup-panel__list">
        @include('filament.components.erp.nfe.produto-lookup')
    </div>

    <div class="erp-nfe-produto-lookup-panel__photo" aria-label="Foto do produto">
        @if ($this->nfeProdutoPreviewFotoUrl)
            <img
                src="{{ $this->nfeProdutoPreviewFotoUrl }}"
                alt="Foto do produto"
                class="erp-nfe-produto-lookup-panel__photo-img"
                wire:key="nfe-produto-foto-{{ md5($this->nfeProdutoPreviewFotoUrl) }}"
            >
        @endif
    </div>
</div>
