@if (filled($this->nfeCceSucessoDetalhe) && ! $this->nfeCceModalOpen && ! $this->nfeCceWhatsAppModalOpen && ! $this->nfeCceEmailModalOpen)

    <div

        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso"

        role="alertdialog"

        aria-labelledby="erp-nfe-fiscal-cce-sucesso-title"

        aria-live="polite"

    >

        <div class="erp-nfe-fiscal-overlay__box">

            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>



            <h2 id="erp-nfe-fiscal-cce-sucesso-title" class="erp-nfe-fiscal-overlay__title">

                CC-e REGISTRADA COM SUCESSO

            </h2>



            <p class="erp-nfe-fiscal-overlay__codigo">{{ $this->nfeCceSucessoDetalhe }}</p>



            <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">

                <button

                    type="button"

                    wire:click="printNfeCartaCorrecao"

                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print"

                    id="erp-nfe-fiscal-cce-sucesso-imprimir"

                >Imprimir</button>



                <button

                    type="button"

                    wire:click="openNfeCceWhatsAppModal"

                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--whatsapp"

                    id="erp-nfe-fiscal-cce-sucesso-whatsapp"

                >WhatsApp</button>



                <button

                    type="button"

                    wire:click="openNfeCceEmailModal"

                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email"

                    id="erp-nfe-fiscal-cce-sucesso-email"

                >E-mail</button>



                <button

                    type="button"

                    wire:click="closeNfeCceSucessoOverlay"

                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"

                    id="erp-nfe-fiscal-cce-sucesso-ok"

                >Sair</button>

            </div>



            <p class="erp-nfe-fiscal-overlay__hint">A Carta de Correção foi registrada na SEFAZ.</p>

        </div>

    </div>

@endif

