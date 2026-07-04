@if ($this->whatsAppModalOpen)

    <div

        class="erp-lookup-modal erp-orc-email-modal erp-orc-whatsapp-modal"

        wire:keydown.escape="closeWhatsAppModal"

        wire:keydown.f5.prevent="sendOrcamentoWhatsApp"

    >

        <div class="erp-lookup-modal__backdrop" wire:click="closeWhatsAppModal"></div>



        <div class="erp-lookup-modal__window erp-orc-whatsapp-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-whatsapp-title">

            <div class="erp-lookup-modal__titlebar">

                <span id="erp-orc-whatsapp-title">Enviar WhatsApp</span>

                <button

                    type="button"

                    class="erp-lookup-modal__close"

                    wire:click="closeWhatsAppModal"

                    title="Fechar"

                >✕</button>

            </div>



            <div class="erp-lookup-modal__body erp-orc-email-modal__body">

                <div class="erp-orc-email-modal__field">

                    <label class="erp-orc-email-modal__label" for="erp-orc-whatsapp-to">WhatsApp:</label>

                    <input

                        id="erp-orc-whatsapp-to"

                        type="text"

                        wire:model="whatsAppTo"

                        class="erp-orc-email-modal__input"

                        data-mask="mobile-phone"

                        autocomplete="off"

                    >

                    @error('whatsAppTo')

                        <span class="erp-orc-email-modal__error">{{ $message }}</span>

                    @enderror

                </div>



                <div class="erp-orc-email-modal__field">

                    <label class="erp-orc-email-modal__label" for="erp-orc-whatsapp-message">Mensagem:</label>

                    <input

                        id="erp-orc-whatsapp-message"

                        type="text"

                        wire:model="whatsAppMessage"

                        class="erp-orc-email-modal__input"

                        maxlength="1000"

                        autocomplete="off"

                    >

                    @error('whatsAppMessage')

                        <span class="erp-orc-email-modal__error">{{ $message }}</span>

                    @enderror

                </div>



                <div class="erp-orc-email-modal__field">

                    <span class="erp-orc-email-modal__label">Anexo:</span>

                    <div class="erp-orc-email-modal__attachments">

                        @if ($this->whatsAppPdfDisplay !== '')

                            <span class="erp-orc-email-modal__attachment is-selected">

                                {{ $this->whatsAppPdfDisplay }}

                            </span>

                        @else

                            <span class="erp-orc-email-modal__attachments-empty">Gerando PDF…</span>

                        @endif

                    </div>

                    <p class="erp-orc-whatsapp-modal__hint">O PDF do orçamento será enviado junto com a mensagem acima.</p>

                </div>

            </div>



            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">

                <button type="button" wire:click="sendOrcamentoWhatsApp" wire:loading.attr="disabled" wire:target="sendOrcamentoWhatsApp" class="erp-pcad-actions__btn" data-erp-key="F5">

                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>

                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendOrcamentoWhatsApp"><kbd>F5</kbd> | Enviar</span>

                    <span class="erp-pcad-actions__label" wire:loading wire:target="sendOrcamentoWhatsApp">Enviando...</span>

                </button>

                <button type="button" wire:click="closeWhatsAppModal" class="erp-pcad-actions__btn" data-erp-key="Escape">

                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>

                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>

                </button>

            </div>

        </div>

    </div>

@endif

