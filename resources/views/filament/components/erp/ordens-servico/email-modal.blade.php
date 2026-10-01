{{-- Sempre no DOM: F9/clique mostra na hora; anexos carregam no request seguinte. --}}
@php
    $emailVisivel = (bool) $this->emailModalOpen;
    $envioTecnica = ($this->emailEnvioModo ?? 'completo') === 'tecnica';
@endphp
<div
    id="erp-os-email-modal"
    class="erp-lookup-modal erp-orc-email-modal erp-orc-envio-modal erp-nfe-whatsapp-modal{{ $emailVisivel ? ' is-visible' : '' }}"
    style="display: {{ $emailVisivel ? 'flex' : 'none' }};"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-os-email-title"
    aria-hidden="{{ $emailVisivel ? 'false' : 'true' }}"
    data-erp-email-modal
    @if ($emailVisivel)
        wire:keydown.escape.window="closeEmailModal"
        @unless ($envioTecnica)
            wire:keydown.f5.prevent.window="sendOrdemServicoEmail"
        @endunless
    @endif
>
    <div class="erp-lookup-modal__backdrop" wire:click="closeEmailModal"></div>

    <div class="erp-lookup-modal__window erp-nfe-whatsapp-modal__window" role="document">
        <div class="erp-lookup-modal__titlebar">
            <span id="erp-os-email-title">{{ $envioTecnica ? 'Enviar OS Técnica ao mecânico' : 'Enviar OS + documentos' }}</span>
            <button
                type="button"
                class="erp-lookup-modal__close"
                wire:click="closeEmailModal"
                title="Fechar"
            >✕</button>
        </div>

        <div class="erp-lookup-modal__body erp-nfe-whatsapp-modal__body erp-orc-email-modal__body">
            @unless ($envioTecnica)
            <div class="erp-nfe-whatsapp-modal__field">
                <label class="erp-nfe-whatsapp-modal__label" for="erp-os-email-to">E-mail:</label>
                <input
                    id="erp-os-email-to"
                    type="email"
                    wire:model="emailTo"
                    class="erp-nfe-whatsapp-modal__input"
                    autocomplete="off"
                >
                @error('emailTo')
                    <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                @enderror
            </div>
            @endunless

            <div class="erp-nfe-whatsapp-modal__field">
                <label class="erp-nfe-whatsapp-modal__label" for="erp-os-whatsapp-to">{{ $envioTecnica ? 'WhatsApp do mecânico:' : 'WhatsApp:' }}</label>
                <input
                    id="erp-os-whatsapp-to"
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

            @unless ($envioTecnica)
            <div class="erp-nfe-whatsapp-modal__field">
                <label class="erp-nfe-whatsapp-modal__label" for="erp-os-email-subject">Assunto:</label>
                <input
                    id="erp-os-email-subject"
                    type="text"
                    wire:model="emailSubject"
                    class="erp-nfe-whatsapp-modal__input"
                    maxlength="255"
                >
                @error('emailSubject')
                    <span class="erp-nfe-whatsapp-modal__error">{{ $message }}</span>
                @enderror
            </div>
            @endunless

            <div class="erp-nfe-whatsapp-modal__field erp-nfe-whatsapp-modal__field--message">
                <label class="erp-nfe-whatsapp-modal__label" for="erp-os-email-message">Mensagem:</label>
                <textarea
                    id="erp-os-email-message"
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
                    @if ($this->emailAttachmentsLoading)
                        <span class="erp-nfe-whatsapp-modal__attachments-empty" data-erp-os-anexos-loading>
                            Preparando {{ $envioTecnica ? 'OS Técnica' : 'anexos (OS, NFS-e, NF-e, boleto)' }}…
                        </span>
                    @else
                        <span class="erp-nfe-whatsapp-modal__attachments-empty" data-erp-os-anexos-shell-loading hidden>
                            Preparando {{ $envioTecnica ? 'OS Técnica' : 'anexos (OS, NFS-e, NF-e, boleto)' }}…
                        </span>
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
                            <span class="erp-nfe-whatsapp-modal__attachments-empty" data-erp-os-anexos-empty>
                                Nenhum anexo.
                            </span>
                        @endforelse
                    @endif
                </div>

                @unless ($envioTecnica)
                <div class="erp-orc-email-modal__attachment-actions">
                    <label class="erp-orc-email-modal__mini-btn">
                        <span aria-hidden="true">+</span>
                        Adicionar anexo
                        <input
                            type="file"
                            wire:model="emailExtraUpload"
                            class="erp-orc-email-modal__file-input"
                            @disabled($this->emailAttachmentsLoading)
                        >
                    </label>
                    <button
                        type="button"
                        wire:click="removeSelectedEmailAttachment"
                        class="erp-orc-email-modal__mini-btn erp-orc-email-modal__mini-btn--danger"
                        @disabled($this->emailAttachmentsLoading || blank($this->emailSelectedAttachmentId))
                    >
                        <span aria-hidden="true">✕</span>
                        Excluir anexo
                    </button>
                </div>
                @endunless

                <div wire:loading wire:target="emailExtraUpload,carregarAnexosEnvioOs" class="erp-orc-email-modal__hint">
                    Carregando anexos…
                </div>

                <p class="erp-nfe-whatsapp-modal__hint">
                    @if ($envioTecnica)
                        Anexa somente a OS Técnica (sem valores). Informe o WhatsApp do mecânico nesta tela; o número não é gravado no cadastro nem na OS.
                    @else
                        Anexa automaticamente OS, NFS-e, NF-e e boleto quando existirem.
                        Pode enviar por e-mail e depois por WhatsApp sem fechar esta tela.
                    @endif
                </p>
            </div>
        </div>

        <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions erp-nfe-whatsapp-modal__actions">
            @unless ($envioTecnica)
            <button
                type="button"
                wire:click="sendOrdemServicoEmail"
                wire:loading.attr="disabled"
                wire:target="sendOrdemServicoEmail,sendOrdemServicoWhatsApp,carregarAnexosEnvioOs"
                wire:loading.class="is-busy"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                data-erp-key="F5"
                @disabled($this->emailAttachmentsLoading)
            >
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✉</span>
                <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendOrdemServicoEmail"><kbd>F5</kbd> | E-mail</span>
                <span class="erp-pcad-actions__label" wire:loading wire:target="sendOrdemServicoEmail">Enviando…</span>
            </button>
            @endunless
            <button
                type="button"
                wire:click="sendOrdemServicoWhatsApp"
                wire:loading.attr="disabled"
                wire:target="sendOrdemServicoEmail,sendOrdemServicoWhatsApp,carregarAnexosEnvioOs"
                wire:loading.class="is-busy"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--whatsapp"
                data-erp-key="WhatsApp"
                @disabled($this->emailAttachmentsLoading)
            >
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--whatsapp" aria-hidden="true">W</span>
                <span class="erp-pcad-actions__label" wire:loading.remove wire:target="sendOrdemServicoWhatsApp">WhatsApp</span>
                <span class="erp-pcad-actions__label" wire:loading wire:target="sendOrdemServicoWhatsApp">Enviando…</span>
            </button>
            <button
                type="button"
                wire:click="closeEmailModal"
                wire:loading.attr="disabled"
                wire:target="sendOrdemServicoEmail,sendOrdemServicoWhatsApp"
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
            wire:target="sendOrdemServicoEmail,sendOrdemServicoWhatsApp"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="erp-orc-email-modal__busy-backdrop" aria-hidden="true"></div>
            <div class="erp-orc-email-modal__busy-panel">
                <div class="erp-orc-email-modal__busy-spinner" aria-hidden="true"></div>
                <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendOrdemServicoEmail">
                    Enviando e-mail…
                </p>
                <p class="erp-orc-email-modal__busy-status" wire:loading wire:target="sendOrdemServicoWhatsApp">
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

@once
    <script>
        (function () {
            window.__erpOsShowEmailModalShell = function () {
                const el = document.getElementById('erp-os-email-modal');
                if (! el) {
                    return;
                }

                el.style.display = 'flex';
                el.classList.add('is-visible');
                el.setAttribute('aria-hidden', 'false');

                const shellLoading = el.querySelector('[data-erp-os-anexos-shell-loading]');
                if (shellLoading) {
                    shellLoading.hidden = false;
                }

                const empty = el.querySelector('[data-erp-os-anexos-empty]');
                if (empty) {
                    empty.hidden = true;
                }
            };

            window.__erpOsHideEmailModalShell = function () {
                const el = document.getElementById('erp-os-email-modal');
                if (! el) {
                    return;
                }

                el.style.display = 'none';
                el.classList.remove('is-visible');
                el.setAttribute('aria-hidden', 'true');
            };
        })();
    </script>
@endonce
