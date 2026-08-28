@if ($this->nfceContadorEmailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfceContadorEmailModal"
        wire:keydown.f5.prevent="sendNfceContadorEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfceContadorEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfce-contador-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfce-contador-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfceContadorEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-contador-competencia">Competência:</label>
                    <input
                        id="nfce-contador-competencia"
                        type="month"
                        wire:model.live="nfceContadorCompetencia"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfceContadorCompetencia')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-contador-email-to">Email:</label>
                    <input
                        id="nfce-contador-email-to"
                        type="email"
                        wire:model="nfceContadorEmailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('nfceContadorEmailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-contador-whatsapp-to">WhatsApp:</label>
                    <input
                        id="nfce-contador-whatsapp-to"
                        type="text"
                        wire:model="nfceContadorWhatsAppTo"
                        class="erp-orc-email-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00) 00000-0000"
                    >
                    @error('nfceContadorWhatsAppTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-contador-email-subject">Assunto:</label>
                    <input
                        id="nfce-contador-email-subject"
                        type="text"
                        wire:model="nfceContadorEmailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfceContadorEmailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-contador-email-message">Mensagem:</label>
                    <input
                        id="nfce-contador-email-message"
                        type="text"
                        wire:model="nfceContadorEmailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfceContadorEmailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        <span class="erp-orc-email-modal__attachment is-selected">
                            {{ $this->nfceContadorPacoteAnexoLabel() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfceContadorEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceContadorEmail,sendNfceContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfceContadorEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfceContadorEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendNfceContadorWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceContadorEmail,sendNfceContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfceContadorWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfceContadorWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfceContadorEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceContadorEmail,sendNfceContadorWhatsApp"
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
                wire:target="sendNfceContadorEmail,sendNfceContadorWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfceContadorEmail">
                        Gerando pacote e enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfceContadorWhatsApp">
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
