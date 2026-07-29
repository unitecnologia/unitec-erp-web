@if ($this->nfeEspelhoEmailModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-espelho-email-modal"
        wire:keydown.escape="closeNfeEspelhoEmailModal"
        wire:keydown.f5.prevent="sendNfeEspelhoEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeEspelhoEmailModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-espelho-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-espelho-email-title">Enviar espelho por E-mail</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeEspelhoEmailModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                @include('filament.components.erp.nfe.partials.espelho-email-destinatario')

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-espelho-email-to">E-mail:</label>
                    <input
                        id="erp-nfe-espelho-email-to"
                        type="email"
                        wire:model="nfeEspelhoEmailTo"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                    >
                    @error('nfeEspelhoEmailTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-espelho-email-subject">Assunto:</label>
                    <input
                        id="erp-nfe-espelho-email-subject"
                        type="text"
                        wire:model="nfeEspelhoEmailSubject"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('nfeEspelhoEmailSubject')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-espelho-email-message">Mensagem:</label>
                    <textarea
                        id="erp-nfe-espelho-email-message"
                        wire:model="nfeEspelhoEmailMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="5000"
                    ></textarea>
                    @error('nfeEspelhoEmailMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->nfeEspelhoEmailAttachments as $attachment)
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">{{ $attachment['display'] }}</span>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="sendNfeEspelhoEmail" wire:loading.attr="disabled" wire:target="sendNfeEspelhoEmail" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeEspelhoEmail"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeEspelhoEmail">Enviando...</span>
                </button>
                <button type="button" wire:click="closeNfeEspelhoEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
