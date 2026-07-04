@if ($this->emailModalOpen)
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeEmailModal"
        wire:keydown.f5.prevent="sendOrcamentoEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-orc-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-to">Email:</label>
                    <input
                        id="erp-orc-email-to"
                        type="email"
                        wire:model="emailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    @error('emailTo')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-subject">Assunto:</label>
                    <input
                        id="erp-orc-email-subject"
                        type="text"
                        wire:model="emailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    @error('emailSubject')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-message">Mensagem:</label>
                    <input
                        id="erp-orc-email-message"
                        type="text"
                        wire:model="emailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    @error('emailMessage')
                        <span class="erp-orc-email-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        @forelse ($this->emailAttachments as $attachment)
                            <button
                                type="button"
                                wire:click="selectEmailAttachment(@js($attachment['id']))"
                                class="erp-orc-email-modal__attachment {{ $this->emailSelectedAttachmentId === $attachment['id'] ? 'is-selected' : '' }}"
                            >
                                {{ $attachment['display'] }}
                            </button>
                        @empty
                            <span class="erp-orc-email-modal__attachments-empty">Nenhum anexo.</span>
                        @endforelse
                    </div>

                    <div class="erp-orc-email-modal__attachment-actions">
                        <label class="erp-orc-email-modal__mini-btn">
                            <span aria-hidden="true">+</span>
                            Adicionar Anexo
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
                            Excluir Anexo
                        </button>
                    </div>

                    <div wire:loading wire:target="emailExtraUpload" class="erp-orc-email-modal__hint">
                        Carregando anexo...
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button type="button" wire:click="sendOrcamentoEmail" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Enviar</span>
                </button>
                <button type="button" wire:click="closeEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
