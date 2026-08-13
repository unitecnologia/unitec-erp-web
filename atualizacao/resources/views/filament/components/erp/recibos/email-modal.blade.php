@if ($this->emailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal erp-orc-envio-modal erp-nfe-whatsapp-modal"
        wire:keydown.escape="closeEmailModal"
        wire:keydown.f5.prevent="sendReciboEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEmailModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-recibo-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-recibo-email-title">Enviar recibo</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body erp-orc-email-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-recibo-email-to">E-mail:</label>
                    <input
                        id="erp-recibo-email-to"
                        type="email"
                        wire:model="emailTo"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                        data-erp-preserve-case
                    >
                    @error('emailTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-recibo-whatsapp-to">WhatsApp:</label>
                    <input
                        id="erp-recibo-whatsapp-to"
                        type="text"
                        wire:model="whatsAppTo"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        inputmode="tel"
                        placeholder="(00)00000-0000"
                    >
                    @error('whatsAppTo')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-recibo-email-subject">Assunto:</label>
                    <input
                        id="erp-recibo-email-subject"
                        type="text"
                        wire:model="emailSubject"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('emailSubject')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-recibo-email-message">Mensagem:</label>
                    <textarea
                        id="erp-recibo-email-message"
                        wire:model="emailMessage"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="5"
                        maxlength="5000"
                    ></textarea>
                    @error('emailMessage')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->emailAttachments as $attachment)
                            <button
                                type="button"
                                wire:click="selectEmailAttachment(@js($attachment['id']))"
                                @class([
                                    'erp-nfe-whatsapp-modal__attachment',
                                    'is-selected' => $this->emailSelectedAttachmentId === $attachment['id'],
                                ])
                            >
                                {{ $attachment['display'] }}
                            </button>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando PDF…</span>
                        @endforelse
                    </div>

                    <div class="erp-orc-email-modal__attachment-actions">
                        <label class="erp-orc-email-modal__mini-btn">
                            <span aria-hidden="true">+</span>
                            Adicionar anexo
                            <input
                                type="file"
                                wire:model="emailExtraUpload"
                                class="erp-orc-email-modal__file-input"
                            >
                        </label>
                        <button
                            type="button"
                            wire:click="removeSelectedEmailAttachment"
                            class="erp-orc-email-modal__mini-btn erp-orc-email-modal__mini-btn--danger"
                            @disabled(blank($this->emailSelectedAttachmentId))
                        >
                            <span aria-hidden="true">✕</span>
                            Excluir anexo
                        </button>
                    </div>

                    <div wire:loading wire:target="emailExtraUpload" class="erp-orc-email-modal__hint">
                        Carregando anexo…
                    </div>

                    <p class="erp-nfe-whatsapp-modal__hint">
                        O PDF do recibo será enviado junto com a mensagem.
                        Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions erp-nfe-whatsapp-modal__actions">
                <button
                    type="button"
                    wire:click="sendReciboEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendReciboEmail,sendReciboWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendReciboEmail"><kbd>F5</kbd> | E-mail</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendReciboEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendReciboWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendReciboEmail,sendReciboWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--whatsapp"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--whatsapp" aria-hidden="true">W</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendReciboWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendReciboWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendReciboEmail,sendReciboWhatsApp"
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
                wire:target="sendReciboEmail,sendReciboWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendReciboEmail">
                        Enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendReciboWhatsApp">
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
