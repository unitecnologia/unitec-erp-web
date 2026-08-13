@if ($this->nfeContadorEmailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfeContadorEmailModal"
        wire:keydown.f5.prevent="sendNfeContadorEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeContadorEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-contador-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-contador-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfeContadorEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfe-contador-competencia">Competência:</label>
                    <input
                        id="nfe-contador-competencia"
                        type="month"
                        wire:model.live="nfeContadorCompetencia"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfeContadorCompetencia')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfe-contador-email-to">Email:</label>
                    <input
                        id="nfe-contador-email-to"
                        type="email"
                        wire:model="nfeContadorEmailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('nfeContadorEmailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfe-contador-whatsapp-to">WhatsApp:</label>
                    <input
                        id="nfe-contador-whatsapp-to"
                        type="text"
                        wire:model="nfeContadorWhatsAppTo"
                        class="erp-orc-email-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00) 00000-0000"
                    >
                    @error('nfeContadorWhatsAppTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfe-contador-email-subject">Assunto:</label>
                    <input
                        id="nfe-contador-email-subject"
                        type="text"
                        wire:model="nfeContadorEmailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfeContadorEmailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfe-contador-email-message">Mensagem:</label>
                    <input
                        id="nfe-contador-email-message"
                        type="text"
                        wire:model="nfeContadorEmailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfeContadorEmailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        <span class="erp-orc-email-modal__attachment is-selected">
                            {{ $this->nfeContadorPacoteAnexoLabel() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfeContadorEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeContadorEmail,sendNfeContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeContadorEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeContadorEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendNfeContadorWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeContadorEmail,sendNfeContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeContadorWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeContadorWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfeContadorEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeContadorEmail,sendNfeContadorWhatsApp"
                    class="erp-pcad-actions__btn"
                    data-erp-key="Escape"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>

            <div
                class="erp-orc-email-modal__busy"
                wire:loading.flex
                wire:target="sendNfeContadorEmail,sendNfeContadorWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfeContadorEmail">
                        Gerando pacote e enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfeContadorWhatsApp">
                        Gerando pacote e enviando WhatsApp…
                    </p>
                    <div
                        class="erp-orc-email-modal__busy-track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Progresso do envio"
                    >
                        <div class="erp-orc-email-modal__busy-bar"></div>
                    </div>
                    <p class="erp-orc-email-modal__busy-hint">Aguarde, não feche esta tela.</p>
                </div>
            </div>
        </div>
    </div>
@endif
