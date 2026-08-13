@if ($this->nfeDanfeEmailModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-danfe-email-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfeDanfeEmailModal"
        wire:keydown.f5.prevent="sendNfeDanfeEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeDanfeEmailModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-danfe-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-danfe-email-title">Enviar nota</span>
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
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfe-danfe-whatsapp-to">WhatsApp:</label>
                    <input
                        id="erp-nfe-danfe-whatsapp-to"
                        type="text"
                        wire:model="nfeDanfeWhatsAppTo"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00)00000-0000"
                    >
                    @error('nfeDanfeWhatsAppTo')
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
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando anexos…</span>
                        @endforelse
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">
                        A DANFE (PDF) e o XML da NF-e serão enviados junto com a mensagem.
                        Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfeDanfeEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeDanfeEmail,sendNfeDanfeWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeDanfeEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeDanfeEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendNfeDanfeWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeDanfeEmail,sendNfeDanfeWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfeDanfeWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfeDanfeWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfeDanfeEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendNfeDanfeEmail,sendNfeDanfeWhatsApp"
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
                wire:target="sendNfeDanfeEmail,sendNfeDanfeWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfeDanfeEmail">
                        Enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfeDanfeWhatsApp">
                        Enviando WhatsApp…
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
