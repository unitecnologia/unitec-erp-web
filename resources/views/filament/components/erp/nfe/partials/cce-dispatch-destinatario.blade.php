<div class="erp-nfe-cce-dispatch__destinatario">
    <span class="erp-nfe-whatsapp-modal__label">Enviar para:</span>

    <div class="erp-nfe-cce-dispatch__options" role="radiogroup" aria-label="Destinatário da CC-e">
        <label class="erp-nfe-cce-dispatch__option">
            <input
                type="radio"
                name="nfe-cce-dispatch-destinatario"
                value="transportadora"
                wire:model.live="nfeCceDispatchDestinatario"
            >
            <span>Transportadora</span>
        </label>

        <label class="erp-nfe-cce-dispatch__option">
            <input
                type="radio"
                name="nfe-cce-dispatch-destinatario"
                value="cliente"
                wire:model.live="nfeCceDispatchDestinatario"
            >
            <span>Cliente</span>
        </label>
    </div>

    @if (filled($this->nfeCceDispatchDestinatarioNome))
        <p class="erp-nfe-cce-dispatch__nome">{{ $this->nfeCceDispatchDestinatarioNome }}</p>
    @endif

    @if (filled($this->nfeCceDispatchDestinatarioAviso))
        <p class="erp-nfe-cce-dispatch__aviso">{{ $this->nfeCceDispatchDestinatarioAviso }}</p>
    @endif
</div>
