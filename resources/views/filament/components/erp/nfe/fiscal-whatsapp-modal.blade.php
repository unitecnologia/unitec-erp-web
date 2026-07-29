@if ($this->nfeWhatsAppModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal"
        wire:keydown.escape="closeNfeWhatsAppModal"
        wire:keydown.f5.prevent="sendNfeWhatsApp"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeWhatsAppModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-whatsapp-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-whatsapp-title">Enviar WhatsApp</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfeWhatsAppModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-whatsapp-to">WhatsApp:</label>
                    <input
                        id="erp-nfe-whatsapp-to"
                        type="text"
                        wire:model="nfeWhatsAppTo"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                    >
                    @error('nfeWhatsAppTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-whatsapp-message">Mensagem:</label>
                    <textarea
                        id="erp-nfe-whatsapp-message"
                        wire:model="nfeWhatsAppMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="1000"
                    ></textarea>
                    @error('nfeWhatsAppMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @if ($this->nfeWhatsAppPdfDisplay !== '')
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">
                                {{ $this->nfeWhatsAppPdfDisplay }}
                            </span>
                        @else
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endif
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">O PDF da DANFE será enviado junto com a mensagem acima.</p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="sendNfeWhatsApp" wire:loading.attr="disabled" wire:target="sendNfeWhatsApp" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeWhatsApp"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeWhatsApp">Enviando...</span>
                </button>
                <button type="button" wire:click="closeNfeWhatsAppModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
