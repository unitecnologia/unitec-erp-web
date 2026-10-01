@if ($this->boletoEnviarModalOpen)
@php
    $boletoEnviarStandalone = (bool) ($boletoEnviarStandalone ?? false);
@endphp
@if ($boletoEnviarStandalone)
@teleport('body')
@endif
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-danfe-email-modal erp-orc-email-modal erp-receber-boleto-enviar-modal{{ $boletoEnviarStandalone ? ' erp-boleto-enviar-modal-standalone' : '' }}"
        wire:keydown.escape="closeBoletoEnviarModal"
        wire:keydown.f5.prevent="sendBoletoEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeBoletoEnviarModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-receber-boleto-enviar-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-receber-boleto-enviar-title">Enviar boleto</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeBoletoEnviarModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-receber-boleto-enviar-email">E-mail:</label>
                    <input
                        id="erp-receber-boleto-enviar-email"
                        type="email"
                        wire:model="boletoEnviarEmail"
                        class="erp-nfe-whatsapp-modal__input"
                        autocomplete="off"
                    >
                    @error('boletoEnviarEmail')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-receber-boleto-enviar-whatsapp">WhatsApp:</label>
                    <input
                        id="erp-receber-boleto-enviar-whatsapp"
                        type="text"
                        wire:model="boletoEnviarWhatsApp"
                        class="erp-nfe-whatsapp-modal__input"
                        data-mask="mobile-phone"
                        autocomplete="off"
                        placeholder="(00)00000-0000"
                    >
                    @error('boletoEnviarWhatsApp')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-receber-boleto-enviar-assunto">Assunto:</label>
                    <input
                        id="erp-receber-boleto-enviar-assunto"
                        type="text"
                        wire:model="boletoEnviarAssunto"
                        class="erp-nfe-whatsapp-modal__input"
                        maxlength="255"
                    >
                    @error('boletoEnviarAssunto')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-receber-boleto-enviar-mensagem">Mensagem:</label>
                    <textarea
                        id="erp-receber-boleto-enviar-mensagem"
                        wire:model="boletoEnviarMensagem"
                        class="erp-nfe-whatsapp-modal__textarea"
                        rows="6"
                        maxlength="5000"
                    ></textarea>
                    @error('boletoEnviarMensagem')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        @forelse ($this->boletoEnviarAttachments as $attachment)
                            <span class="erp-nfe-whatsapp-modal__attachment is-selected">{{ $attachment['display'] }}</span>
                        @empty
                            <span class="erp-nfe-whatsapp-modal__attachments-empty">Gerando anexos…</span>
                        @endforelse
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">
                        O boleto (PDF), o pedido/DAV ou OS vinculado e, se houver, DANFE/XML da NF-e serão enviados juntos.
                        Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button
                    type="button"
                    wire:click="sendBoletoEmail"
                    wire:loading.attr="disabled"
                    wire:target="sendBoletoEmail,sendBoletoWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendBoletoEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendBoletoEmail">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="sendBoletoWhatsApp"
                    wire:loading.attr="disabled"
                    wire:target="sendBoletoEmail,sendBoletoWhatsApp"
                    wire:loading.class="is-busy"
                    class="erp-pcad-actions__btn"
                    data-erp-key="WhatsApp"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendBoletoWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendBoletoWhatsApp">Enviando…</span>
                </button>
                <button
                    type="button"
                    wire:click="closeBoletoEnviarModal"
                    wire:loading.attr="disabled"
                    wire:target="sendBoletoEmail,sendBoletoWhatsApp"
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
                wire:target="sendBoletoEmail,sendBoletoWhatsApp"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
                <div class="erp-orc-email-modal__busy-panel">
                    <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendBoletoEmail">
                        Enviando e-mail…
                    </p>
                    <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendBoletoWhatsApp">
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
@if ($boletoEnviarStandalone)
@endteleport
@endif
@endif
