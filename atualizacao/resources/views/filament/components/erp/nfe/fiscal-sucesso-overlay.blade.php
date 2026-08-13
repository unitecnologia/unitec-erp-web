@if (filled($this->nfeFiscalSucessoDetalhe) && ! $this->nfeWhatsAppModalOpen && ! $this->nfeDanfeEmailModalOpen && ! $this->nfeCancelModalOpen && ! $this->nfeInutilizarModalOpen && ! $this->nfeCceModalOpen && ! filled($this->nfeCceSucessoDetalhe))
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso"
        role="alertdialog"
        aria-labelledby="erp-nfe-fiscal-sucesso-title"
        aria-live="polite"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>

            <h2 id="erp-nfe-fiscal-sucesso-title" class="erp-nfe-fiscal-overlay__title">
                NF-E TRANSMITIDA COM SUCESSO
            </h2>

            <p class="erp-nfe-fiscal-overlay__codigo">{{ $this->nfeFiscalSucessoDetalhe }}</p>

            <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
                <button
                    type="button"
                    wire:click="printNfeDanfe"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print"
                    id="erp-nfe-fiscal-sucesso-imprimir"
                >Imprimir</button>

                <button
                    type="button"
                    wire:click="openNfeDanfeEmailModal"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email"
                    id="erp-nfe-fiscal-sucesso-enviar"
                >Enviar</button>

                <button
                    type="button"
                    wire:click="acknowledgeNfeFiscalSucessoOverlay"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                    id="erp-nfe-fiscal-sucesso-ok"
                >Sair</button>
            </div>

            <p class="erp-nfe-fiscal-overlay__hint">A nota já consta como transmitida na SEFAZ.</p>
        </div>
    </div>
@endif
