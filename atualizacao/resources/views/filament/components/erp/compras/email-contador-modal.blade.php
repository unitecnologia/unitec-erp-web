@if ($this->compraContadorEmailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeCompraContadorEmailModal"
        wire:keydown.f5.prevent="sendCompraContadorEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCompraContadorEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-compra-contador-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-compra-contador-email-title">Fechar Mês — Enviar ao Contador</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeCompraContadorEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="compra-contador-competencia">Competência:</label>
                    <input
                        id="compra-contador-competencia"
                        type="month"
                        wire:model.live="compraContadorCompetencia"
                        class="erp-orc-email-modal__input"
                    >
                    @error('compraContadorCompetencia')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="compra-contador-email-to">Email:</label>
                    <input
                        id="compra-contador-email-to"
                        type="email"
                        wire:model="compraContadorEmailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('compraContadorEmailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="compra-contador-whatsapp-to">WhatsApp:</label>
                    <input
                        id="compra-contador-whatsapp-to"
                        type="text"
                        wire:model="compraContadorWhatsAppTo"
                        class="erp-orc-email-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00) 00000-0000"
                    >
                    @error('compraContadorWhatsAppTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="compra-contador-email-subject">Assunto:</label>
                    <input
                        id="compra-contador-email-subject"
                        type="text"
                        wire:model="compraContadorEmailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('compraContadorEmailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="compra-contador-email-message">Mensagem:</label>
                    <input
                        id="compra-contador-email-message"
                        type="text"
                        wire:model="compraContadorEmailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('compraContadorEmailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        <span class="erp-orc-email-modal__attachment is-selected">
                            {{ $this->compraContadorPacoteAnexoLabel() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button
                    type="button"
                    wire:click="sendCompraContadorEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendCompraContadorEmail,sendCompraContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendCompraContadorEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendCompraContadorEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendCompraContadorWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendCompraContadorEmail,sendCompraContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendCompraContadorWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendCompraContadorWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeCompraContadorEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendCompraContadorEmail,sendCompraContadorWhatsApp"
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
                wire:target="sendCompraContadorEmail,sendCompraContadorWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendCompraContadorEmail">
                        Gerando pacote e enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendCompraContadorWhatsApp">
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
