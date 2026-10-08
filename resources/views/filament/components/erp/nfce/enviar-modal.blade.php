@if ($this->nfceEnviarModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-danfe-email-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfceEnviarModal"
        wire:keydown.f5.prevent="sendNfceEnviarEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfceEnviarModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfce-enviar-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfce-enviar-title">Enviar nota</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfceEnviarModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfce-enviar-email">E-mail:</label>
                    <input
                        id="erp-nfce-enviar-email"
                        type="email"
                        wire:model="nfceEnviarEmailTo"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                    >
                    @error('nfceEnviarEmailTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfce-enviar-whatsapp">WhatsApp:</label>
                    <input
                        id="erp-nfce-enviar-whatsapp"
                        type="text"
                        wire:model="nfceEnviarWhatsAppTo"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        inputmode="tel"
                        placeholder="(00)00000-0000"
                    >
                    @error('nfceEnviarWhatsAppTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfce-enviar-assunto">Assunto:</label>
                    <input
                        id="erp-nfce-enviar-assunto"
                        type="text"
                        wire:model="nfceEnviarSubject"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('nfceEnviarSubject')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfce-enviar-mensagem">Mensagem:</label>
                    <textarea
                        id="erp-nfce-enviar-mensagem"
                        wire:model="nfceEnviarMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="5000"
                    ></textarea>
                    @error('nfceEnviarMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->nfceEnviarAttachments as $attachment)
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected" title="{{ $attachment['name'] }}">{{ $attachment['display'] }}</span>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Nenhum anexo.</span>
                        @endforelse
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">
                        A DANFE NFC-e (PDF) e o XML autorizado serão enviados junto com a mensagem.
                        Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfceEnviarEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceEnviarEmail,sendNfceEnviarWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfceEnviarEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfceEnviarEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendNfceEnviarWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceEnviarEmail,sendNfceEnviarWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfceEnviarWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfceEnviarWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfceEnviarModal"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceEnviarEmail,sendNfceEnviarWhatsApp"
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
                wire:target="sendNfceEnviarEmail,sendNfceEnviarWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfceEnviarEmail">
                        Enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfceEnviarWhatsApp">
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
