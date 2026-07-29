@if ($this->nfeCceWhatsAppModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-cce-whatsapp-modal"
        wire:keydown.escape="closeNfeCceWhatsAppModal"
        wire:keydown.f5.prevent="sendNfeCceWhatsApp"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeCceWhatsAppModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-cce-whatsapp-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-cce-whatsapp-title">Enviar CC-e por WhatsApp</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeCceWhatsAppModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                @include('filament.components.erp.nfe.partials.cce-dispatch-destinatario')

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-cce-whatsapp-to">WhatsApp:</label>
                    <input
                        id="erp-nfe-cce-whatsapp-to"
                        type="text"
                        wire:model="nfeCceWhatsAppTo"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                    >
                    @error('nfeCceWhatsAppTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-cce-whatsapp-message">Mensagem:</label>
                    <textarea
                        id="erp-nfe-cce-whatsapp-message"
                        wire:model="nfeCceWhatsAppMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="1000"
                    ></textarea>
                    @error('nfeCceWhatsAppMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @if ($this->nfeCceWhatsAppPdfDisplay !== '')
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">
                                {{ $this->nfeCceWhatsAppPdfDisplay }}
                            </span>
                        @else
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endif
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">O PDF da CC-e será enviado junto com a mensagem acima.</p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="sendNfeCceWhatsApp" wire:loading.attr="disabled" wire:target="sendNfeCceWhatsApp" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeCceWhatsApp"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeCceWhatsApp">Enviando...</span>
                </button>
                <button type="button" wire:click="closeNfeCceWhatsAppModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
