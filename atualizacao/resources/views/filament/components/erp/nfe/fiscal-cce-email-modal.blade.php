@if ($this->nfeCceEmailModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-cce-email-modal"
        wire:keydown.escape="closeNfeCceEmailModal"
        wire:keydown.f5.prevent="sendNfeCceEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeCceEmailModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-cce-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-cce-email-title">Enviar CC-e por E-mail</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeCceEmailModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                @include('filament.components.erp.nfe.partials.cce-dispatch-destinatario')

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-cce-email-to">E-mail:</label>
                    <input
                        id="erp-nfe-cce-email-to"
                        type="email"
                        wire:model="nfeCceEmailTo"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                    >
                    @error('nfeCceEmailTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-cce-email-subject">Assunto:</label>
                    <input
                        id="erp-nfe-cce-email-subject"
                        type="text"
                        wire:model="nfeCceEmailSubject"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('nfeCceEmailSubject')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-cce-email-message">Mensagem:</label>
                    <textarea
                        id="erp-nfe-cce-email-message"
                        wire:model="nfeCceEmailMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="5000"
                    ></textarea>
                    @error('nfeCceEmailMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->nfeCceEmailAttachments as $attachment)
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">{{ $attachment['display'] }}</span>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="sendNfeCceEmail" wire:loading.attr="disabled" wire:target="sendNfeCceEmail" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeCceEmail"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeCceEmail">Enviando...</span>
                </button>
                <button type="button" wire:click="closeNfeCceEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
