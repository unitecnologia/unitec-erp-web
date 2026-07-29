@if ($this->nfceClienteEmailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeNfceClienteEmailModal"
        wire:keydown.f5.prevent="sendNfceClienteEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfceClienteEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfce-cliente-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfce-cliente-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeNfceClienteEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-cliente-email-to">Email:</label>
                    <input
                        id="nfce-cliente-email-to"
                        type="email"
                        wire:model="nfceClienteEmailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('nfceClienteEmailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-cliente-email-subject">Assunto:</label>
                    <input
                        id="nfce-cliente-email-subject"
                        type="text"
                        wire:model="nfceClienteEmailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfceClienteEmailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="nfce-cliente-email-message">Mensagem:</label>
                    <input
                        id="nfce-cliente-email-message"
                        type="text"
                        wire:model="nfceClienteEmailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('nfceClienteEmailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        @forelse ($this->nfceClienteEmailAttachments as $attachment)
                            <button
                                type="button"
                                wire:click="selectNfceClienteEmailAttachment(@js($attachment['id']))"
                                class="erp-orc-email-modal__attachment {{ $this->nfceClienteEmailSelectedAttachmentId === $attachment['id'] ? 'is-selected' : '' }}"
                            >
                                {{ $attachment['display'] }}
                            </button>
                        @empty
                            <span class="erp-orc-email-modal__attachments-empty">Nenhum anexo.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button
                    type="button"
                    wire:click="sendNfceClienteEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendNfceClienteEmail"
                    class="erp-pcad-actions__btn"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendNfceClienteEmail"><kbd>F5</kbd> | Enviar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendNfceClienteEmail">Enviando…</span>
                </button>
                <button type="button" wire:click="closeNfceClienteEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
