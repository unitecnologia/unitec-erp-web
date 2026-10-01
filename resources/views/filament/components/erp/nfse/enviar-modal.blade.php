@if ($this->nfseEnviarModalOpen)
    <div
        class="erp-lookup-modal erp-nfe-whatsapp-modal erp-nfe-danfe-email-modal erp-orc-email-modal erp-nfse-enviar-modal"
        wire:keydown.escape="fecharNfseEnviar"
        wire:keydown.f5.prevent="enviarNfseEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharNfseEnviar"></div>
        <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfse-enviar-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-enviar-title">Enviar nota</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharNfseEnviar" title="Fechar">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body">
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfse-enviar-email">E-mail:</label>
                    <input id="erp-nfse-enviar-email" type="email" wire:model="nfseEnviarEmail" class="erp-nfe-whatsapp-modal__input" autocomplete="off">
                    @error('nfseEnviarEmail')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfse-enviar-whatsapp">WhatsApp:</label>
                    <input id="erp-nfse-enviar-whatsapp" type="text" wire:model="nfseEnviarWhatsApp" class="erp-nfe-whatsapp-modal__input" data-mask="mobile-phone" autocomplete="off" placeholder="(00)00000-0000">
                    @error('nfseEnviarWhatsApp')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="erp-nfe-whatsapp-modal__field">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfse-enviar-assunto">Assunto:</label>
                    <input id="erp-nfse-enviar-assunto" type="text" wire:model="nfseEnviarAssunto" class="erp-nfe-whatsapp-modal__input" maxlength="255">
                    @error('nfseEnviarAssunto')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                    <label class="erp-nfe-whatsapp-modal__label" for="erp-nfse-enviar-mensagem">Mensagem:</label>
                    <textarea id="erp-nfse-enviar-mensagem" wire:model="nfseEnviarMensagem" class="erp-nfe-whatsapp-modal__textarea" rows="6" maxlength="5000"></textarea>
                    @error('nfseEnviarMensagem')
                        <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="erp-nfe-whatsapp-modal__field">
                    <span class="erp-nfe-whatsapp-modal__label">Anexo:</span>
                    <div class="erp-nfe-whatsapp-modal__attachments">
                        <span class="erp-nfe-whatsapp-modal__attachment is-selected">PDF da NFS-e</span>
                        <span class="erp-nfe-whatsapp-modal__attachment is-selected">XML autorizado</span>
                    </div>
                    <p class="erp-nfe-whatsapp-modal__hint">Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.</p>
                    @if (filled($this->nfseEnviarStatus))
                        <p class="erp-nfe-whatsapp-modal__hint">{{ $this->nfseEnviarStatus }}</p>
                    @endif
                </div>
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-whatsapp-modal__actions">
                <button type="button" wire:click="enviarNfseEmail" wire:loading.attr="disabled" wire:target="enviarNfseEmail,enviarNfseWhatsApp" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon">✉</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="enviarNfseEmail"><kbd>F5</kbd> | Email</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="enviarNfseEmail">Enviando…</span>
                </button>
                <button type="button" wire:click="enviarNfseWhatsApp" wire:loading.attr="disabled" wire:target="enviarNfseEmail,enviarNfseWhatsApp" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon">✆</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="enviarNfseWhatsApp">WhatsApp</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="enviarNfseWhatsApp">Enviando…</span>
                </button>
                <button type="button" wire:click="fecharNfseEnviar" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
