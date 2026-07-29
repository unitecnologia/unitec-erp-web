@if ($this->nfeDanfeEmailModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-danfe-email-modal"
        wire:keydown.escape="closeNfeDanfeEmailModal"
        wire:keydown.f5.prevent="sendNfeDanfeEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeDanfeEmailModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-danfe-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-danfe-email-title">Enviar DANFE por E-mail</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeDanfeEmailModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-danfe-email-to">E-mail:</label>
                    <input
                        id="erp-nfe-danfe-email-to"
                        type="email"
                        wire:model="nfeDanfeEmailTo"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                    >
                    @error('nfeDanfeEmailTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-danfe-email-subject">Assunto:</label>
                    <input
                        id="erp-nfe-danfe-email-subject"
                        type="text"
                        wire:model="nfeDanfeEmailSubject"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('nfeDanfeEmailSubject')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-danfe-email-message">Mensagem:</label>
                    <textarea
                        id="erp-nfe-danfe-email-message"
                        wire:model="nfeDanfeEmailMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="5000"
                    ></textarea>
                    @error('nfeDanfeEmailMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->nfeDanfeEmailAttachments as $attachment)
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">{{ $attachment['display'] }}</span>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="sendNfeDanfeEmail" wire:loading.attr="disabled" wire:target="sendNfeDanfeEmail" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeDanfeEmail"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeDanfeEmail">Enviando...</span>
                </button>
                <button type="button" wire:click="closeNfeDanfeEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
