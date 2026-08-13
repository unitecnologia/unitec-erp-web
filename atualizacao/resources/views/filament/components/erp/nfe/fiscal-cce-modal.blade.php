@if ($this->nfeCceModalOpen)
    @php
        $minCorrecao = \App\Support\Erp\Nfe\NfeCartaCorrecaoMotivo::MIN_LENGTH;
        $maxCorrecao = \App\Support\Erp\Nfe\NfeCartaCorrecaoMotivo::MAX_LENGTH;
        $correcaoLength = mb_strlen(trim($this->nfeCceCorrecao), 'UTF-8');
    @endphp

    <div class="erp-lookup-modal erp-nfce-fiscal-modal erp-nfe-cce-modal" wire:keydown.escape.window="closeNfeCceModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeCceModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-cce-modal-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-cce-modal-title">Carta de Correção — CC-e</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeCceModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body">
                <p class="erp-nfce-fiscal-modal__hint">
                    <strong>{{ $this->nfeCceNumeroDetalhe }}</strong><br>
                    Chave selecionada<br>
                    {{ $this->nfeCceChaveFormatada ?: '—' }}
                </p>

                <div class="erp-nfce-fiscal-modal__field-group">
                    <label class="erp-nfce-fiscal-modal__label" for="erp-nfe-cce-correcao">Texto da correção</label>
                    <textarea
                        id="erp-nfe-cce-correcao"
                        wire:model.live.debounce.150ms="nfeCceCorrecao"
                        wire:keydown.ctrl.enter.prevent="confirmCartaCorrecaoNfe"
                        class="erp-nfce-fiscal-modal__textarea"
                        rows="6"
                        maxlength="{{ $maxCorrecao }}"
                        placeholder="Mínimo {{ $minCorrecao }} caracteres"
                    ></textarea>
                    <p @class([
                        'erp-nfce-fiscal-modal__counter',
                        'erp-nfce-fiscal-modal__counter--ok' => $correcaoLength >= $minCorrecao,
                    ])>
                        {{ $correcaoLength }}/{{ $maxCorrecao }}
                        @if ($correcaoLength < $minCorrecao)
                            — faltam {{ $minCorrecao - $correcaoLength }} caracteres
                        @endif
                    </p>
                </div>

                <p class="erp-nfce-fiscal-modal__hint erp-nfce-fiscal-modal__hint--legal">
                    A Carta de Correção não altera valores de impostos, remetente/destinatário ou datas de emissão/saída.
                </p>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button
                    type="button"
                    wire:click="confirmCartaCorrecaoNfe"
                    wire:loading.attr="disabled"
                    wire:target="confirmCartaCorrecaoNfe"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    @disabled($correcaoLength < $minCorrecao)
                >
                    <span class="erp-pcad-actions__icon">📝</span>
                    <span wire:loading.remove wire:target="confirmCartaCorrecaoNfe" class="erp-pcad-actions__label">Enviar CC-e</span>
                    <span wire:loading wire:target="confirmCartaCorrecaoNfe" class="erp-pcad-actions__label">Enviando…</span>
                </button>
                <button type="button" wire:click="closeNfeCceModal" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon">↩</span>
                    <span class="erp-pcad-actions__label">Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
