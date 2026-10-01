@if ($this->pixRenovacaoOpen)
    <div
        class="erp-dash-pix"
        x-data="{
            copied: false,
            async copyPix(code) {
                if (!code) return;
                try {
                    await navigator.clipboard.writeText(code);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1800);
                } catch (e) {
                    const input = this.$refs.pixInput;
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }
            }
        }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-dash-pix-title"
    >
        <button
            type="button"
            class="erp-dash-pix__backdrop"
            wire:click="fecharRenovacaoPix"
            aria-label="Fechar"
        ></button>

        <div class="erp-dash-pix__card">
            <div class="erp-dash-pix__head">
                <div>
                    <p class="erp-dash-pix__eyebrow">Licença</p>
                    <h2 id="erp-dash-pix-title" class="erp-dash-pix__title">Renovar mensalidade</h2>
                </div>
                <button
                    type="button"
                    class="erp-dash-pix__close"
                    wire:click="fecharRenovacaoPix"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            @if ($this->pixLoading)
                <p class="erp-dash-pix__loading">Gerando QR Code Pix…</p>
            @elseif (filled($this->pixQrDataUrl) || filled($this->pixBrCode))
                <div class="erp-dash-pix__body">
                    <div class="erp-dash-pix__amount-row">
                        <strong>Pagar com Pix</strong>
                        @if (filled($this->pixAmount))
                            <span>{{ $this->pixAmount }}</span>
                        @endif
                    </div>

                    @if (filled($this->pixDescription))
                        <p class="erp-dash-pix__desc">{{ $this->pixDescription }}</p>
                    @endif

                    @if (filled($this->pixQrDataUrl))
                        <img
                            class="erp-dash-pix__qr"
                            src="{{ $this->pixQrDataUrl }}"
                            alt="QR Code Pix"
                            width="168"
                            height="168"
                        >
                    @endif

                    <p class="erp-dash-pix__hint">Escaneie o QR ou copie o código Pix.</p>

                    @if (filled($this->pixBrCode))
                        <div class="erp-dash-pix__copia">
                            <input
                                x-ref="pixInput"
                                class="erp-dash-pix__copia-input"
                                type="text"
                                readonly
                                value="{{ $this->pixBrCode }}"
                                aria-label="Código Pix copia e cola"
                            >
                            <button
                                type="button"
                                class="erp-dash-pix__btn erp-dash-pix__btn--ghost"
                                @click="copyPix(@js($this->pixBrCode))"
                            >
                                <span x-show="!copied">Copiar</span>
                                <span x-show="copied" x-cloak>Copiado</span>
                            </button>
                        </div>
                    @endif
                </div>
            @elseif (filled($this->pixMessage))
                <p class="erp-dash-pix__feedback">{{ $this->pixMessage }}</p>
            @endif

            @if (filled($this->pixFeedback))
                <p class="erp-dash-pix__feedback">{{ $this->pixFeedback }}</p>
            @endif

            <div class="erp-dash-pix__actions">
                @if ($this->pixInvoiceId > 0)
                    <button
                        type="button"
                        class="erp-dash-pix__btn erp-dash-pix__btn--accent"
                        wire:click="verificarPagamentoRenovacao"
                        wire:loading.attr="disabled"
                        wire:target="verificarPagamentoRenovacao"
                    >
                        <span wire:loading.remove wire:target="verificarPagamentoRenovacao">Já paguei — verificar</span>
                        <span wire:loading wire:target="verificarPagamentoRenovacao">Verificando…</span>
                    </button>
                @endif

                <button
                    type="button"
                    class="erp-dash-pix__btn erp-dash-pix__btn--ghost"
                    wire:click="abrirRenovacaoPix"
                    wire:loading.attr="disabled"
                    wire:target="abrirRenovacaoPix"
                >
                    <span wire:loading.remove wire:target="abrirRenovacaoPix">Atualizar QR</span>
                    <span wire:loading wire:target="abrirRenovacaoPix">Atualizando…</span>
                </button>

                <button
                    type="button"
                    class="erp-dash-pix__btn erp-dash-pix__btn--ghost"
                    wire:click="fecharRenovacaoPix"
                >Fechar</button>
            </div>
        </div>
    </div>
@endif
