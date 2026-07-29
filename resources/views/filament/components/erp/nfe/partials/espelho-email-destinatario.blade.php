<div class="erp-nfe-cce-dispatch__destinatario">
    <span class="erp-nfe-whatsapp-modal__label">Enviar para:</span>

    <div class="erp-nfe-cce-dispatch__options" role="radiogroup" aria-label="Destinatário do espelho">
        <label class="erp-nfe-cce-dispatch__option">
            <input
                type="radio"
                name="nfe-espelho-email-destinatario"
                value="cliente"
                wire:model.live="nfeEspelhoEmailDestinatario"
            >
            <span>Cliente</span>
        </label>

        <label class="erp-nfe-cce-dispatch__option">
            <input
                type="radio"
                name="nfe-espelho-email-destinatario"
                value="fornecedor"
                wire:model.live="nfeEspelhoEmailDestinatario"
            >
            <span>Fornecedor</span>
        </label>
    </div>

    @if (filled($this->nfeEspelhoDispatchDestinatarioNome))
        <p class="erp-nfe-cce-dispatch__nome">{{ $this->nfeEspelhoDispatchDestinatarioNome }}</p>
    @endif

    @if (filled($this->nfeEspelhoDispatchDestinatarioAviso))
        <p class="erp-nfe-cce-dispatch__aviso">{{ $this->nfeEspelhoDispatchDestinatarioAviso }}</p>
    @endif
</div>
