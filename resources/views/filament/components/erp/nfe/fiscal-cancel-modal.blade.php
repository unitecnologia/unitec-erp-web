@if ($this->nfeCancelModalOpen)
    @php
        $minMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MIN_LENGTH;
        $maxMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MAX_LENGTH;
        $cancelLength = mb_strlen(trim($this->nfeCancelJustificativa), 'UTF-8');
    @endphp

    <div class="erp-lookup-modal erp-nfce-fiscal-modal erp-nfe-cancel-modal" wire:keydown.escape.window="closeNfeCancelModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeCancelModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-cancel-modal-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-cancel-modal-title">Cancelar NF-e</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeCancelModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body">
                <p class="erp-nfce-fiscal-modal__hint">
                    <strong>{{ $this->nfeCancelNumeroDetalhe }}</strong><br>
                    Chave selecionada<br>
                    {{ $this->nfeCancelChaveFormatada ?: '—' }}
                </p>

                <div class="erp-nfce-fiscal-modal__field-group">
                    <label class="erp-nfce-fiscal-modal__label" for="erp-nfe-cancel-justificativa">Justificativa do cancelamento</label>
                    <textarea
                        id="erp-nfe-cancel-justificativa"
                        wire:model.live.debounce.150ms="nfeCancelJustificativa"
                        wire:keydown.ctrl.enter.prevent="confirmCancelarNfe"
                        class="erp-nfce-fiscal-modal__textarea"
                        rows="4"
                        maxlength="{{ $maxMotivo }}"
                        placeholder="Mínimo {{ $minMotivo }} caracteres"
                    ></textarea>
                    <p @class([
                        'erp-nfce-fiscal-modal__counter',
                        'erp-nfce-fiscal-modal__counter--ok' => $cancelLength >= $minMotivo,
                    ])>
                        {{ $cancelLength }}/{{ $maxMotivo }}
                        @if ($cancelLength < $minMotivo)
                            — faltam {{ $minMotivo - $cancelLength }} caracteres
                        @endif
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button
                    type="button"
                    wire:click="confirmCancelarNfe"
                    wire:loading.attr="disabled"
                    wire:target="confirmCancelarNfe"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                    @disabled($cancelLength < $minMotivo)
                >
                    <span class="erp-pcad-actions__icon">✕</span>
                    <span wire:loading.remove wire:target="confirmCancelarNfe" class="erp-pcad-actions__label">Confirmar cancelamento</span>
                    <span wire:loading wire:target="confirmCancelarNfe" class="erp-pcad-actions__label">Cancelando…</span>
                </button>
                <button type="button" wire:click="closeNfeCancelModal" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon">↩</span>
                    <span class="erp-pcad-actions__label">Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
