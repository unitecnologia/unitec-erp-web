@if ($this->nfseContadorEmailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfseContadorEmailModal"
        wire:keydown.f5.prevent="sendNfseContadorEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfseContadorEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfse-contador-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-contador-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfseContadorEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfse-contador-competencia">Competência:</label>
                    <input
                        id="nfse-contador-competencia"
                        type="month"
                        wire:model.live="nfseContadorCompetencia"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfseContadorCompetencia')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfse-contador-email-to">Email:</label>
                    <input
                        id="nfse-contador-email-to"
                        type="email"
                        wire:model="nfseContadorEmailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('nfseContadorEmailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfse-contador-whatsapp-to">WhatsApp:</label>
                    <input
                        id="nfse-contador-whatsapp-to"
                        type="text"
                        wire:model="nfseContadorWhatsAppTo"
                        class="erp-orc-email-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00) 00000-0000"
                    >
                    @error('nfseContadorWhatsAppTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfse-contador-email-subject">Assunto:</label>
                    <input
                        id="nfse-contador-email-subject"
                        type="text"
                        wire:model="nfseContadorEmailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfseContadorEmailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfse-contador-email-message">Mensagem:</label>
                    <input
                        id="nfse-contador-email-message"
                        type="text"
                        wire:model="nfseContadorEmailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfseContadorEmailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        <span class="erp-orc-email-modal__attachment is-selected">
                            {{ $this->nfseContadorPacoteAnexoLabel() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfseContadorEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfseContadorEmail,sendNfseContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfseContadorEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfseContadorEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendNfseContadorWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendNfseContadorEmail,sendNfseContadorWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfseContadorWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfseContadorWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeNfseContadorEmailModal"
                    wire:loading.attr="disabled"
                    wire:target="sendNfseContadorEmail,sendNfseContadorWhatsApp"
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
                wire:target="sendNfseContadorEmail,sendNfseContadorWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfseContadorEmail">
                        Gerando pacote e enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendNfseContadorWhatsApp">
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

@include('filament.components.erp.aviso-modal', [
    'open' => $this->nfseContadorPendenciaAvisoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-nfse-contador-pendencia-title',
    'title' => 'NÃO É POSSÍVEL GERAR O PDF',
    'lines' => $this->nfseContadorPendenciaAvisoLines,
    'hint' => 'Este aviso só some ao clicar em OK.',
    'primaryLabel' => 'OK',
    'primaryAction' => 'closeNfseContadorPendenciaAviso',
    'escapeAction' => 'closeNfseContadorPendenciaAviso',
    'backdropAction' => 'closeNfseContadorPendenciaAviso',
])
