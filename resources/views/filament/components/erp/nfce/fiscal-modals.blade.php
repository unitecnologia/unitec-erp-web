@if (in_array($this->nfceFiscalModal, ['cancelar', 'inutilizar'], true))
    @php
        $minMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MIN_LENGTH;
        $maxMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MAX_LENGTH;
        $cancelLength = mb_strlen(trim($this->nfceCancelJustificativa), 'UTF-8');
        $inutilLength = mb_strlen(trim($this->nfceInutilizarJustificativa), 'UTF-8');
        $chaveSelecionada = $this->highlightedChave ?: '—';
    @endphp

    <div class="erp-lookup-modal erp-nfce-fiscal-modal" wire:keydown.escape.window="closeNfceFiscalModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfceFiscalModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfce-fiscal-modal-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfce-fiscal-modal-title">
                    @if ($this->nfceFiscalModal === 'cancelar')
                        Cancelar NFC-e
                    @else
                        Inutilizar numeração
                    @endif
                </span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfceFiscalModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body">
                @if ($this->nfceFiscalModal === 'cancelar')
                    <p class="erp-nfce-fiscal-modal__hint">
                        <strong>Chave selecionada</strong><br>
                        {{ $chaveSelecionada }}
                    </p>

                    <div class="erp-nfce-fiscal-modal__field-group">
                        <label class="erp-nfce-fiscal-modal__label" for="nfce-cancel-justificativa">Justificativa do cancelamento</label>
                        <textarea
                            id="nfce-cancel-justificativa"
                            wire:model.live.debounce.150ms="nfceCancelJustificativa"
                            wire:keydown.ctrl.enter.prevent="confirmCancelarNfce"
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
                @else
                    <div class="erp-nfce-fiscal-modal__grid">
                        <label class="erp-nfce-fiscal-modal__field">
                            <span>Série</span>
                            <input type="number" min="1" wire:model="nfceInutilizarSerie" class="erp-nfce-fiscal-modal__input">
                        </label>
                        <label class="erp-nfce-fiscal-modal__field">
                            <span>Nº inicial</span>
                            <input type="number" min="1" wire:model="nfceInutilizarNumeroIni" class="erp-nfce-fiscal-modal__input">
                        </label>
                        <label class="erp-nfce-fiscal-modal__field">
                            <span>Nº final</span>
                            <input type="number" min="1" wire:model="nfceInutilizarNumeroFim" class="erp-nfce-fiscal-modal__input" placeholder="Opcional">
                        </label>
                    </div>

                    <div class="erp-nfce-fiscal-modal__field-group">
                        <label class="erp-nfce-fiscal-modal__label" for="nfce-inutilizar-justificativa">Justificativa</label>
                        <textarea
                            id="nfce-inutilizar-justificativa"
                            wire:model.live.debounce.150ms="nfceInutilizarJustificativa"
                            wire:keydown.ctrl.enter.prevent="confirmInutilizarNfce"
                            class="erp-nfce-fiscal-modal__textarea"
                            rows="4"
                            maxlength="{{ $maxMotivo }}"
                            placeholder="Mínimo {{ $minMotivo }} caracteres"
                        ></textarea>
                        <p @class([
                            'erp-nfce-fiscal-modal__counter',
                            'erp-nfce-fiscal-modal__counter--ok' => $inutilLength >= $minMotivo,
                        ])>
                            {{ $inutilLength }}/{{ $maxMotivo }}
                            @if ($inutilLength < $minMotivo)
                                — faltam {{ $minMotivo - $inutilLength }} caracteres
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                @if ($this->nfceFiscalModal === 'cancelar')
                    <button
                        type="button"
                        wire:click="confirmCancelarNfce"
                        wire:loading.attr="disabled"
                        wire:target="confirmCancelarNfce"
                        class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                        @disabled($cancelLength < $minMotivo)
                    >
                        <span class="erp-pcad-actions__icon">✕</span>
                        <span wire:loading.remove wire:target="confirmCancelarNfce" class="erp-pcad-actions__label">Confirmar cancelamento</span>
                        <span wire:loading wire:target="confirmCancelarNfce" class="erp-pcad-actions__label">Cancelando…</span>
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="confirmInutilizarNfce"
                        wire:loading.attr="disabled"
                        wire:target="confirmInutilizarNfce"
                        class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                        @disabled($inutilLength < $minMotivo)
                    >
                        <span class="erp-pcad-actions__icon">✓</span>
                        <span wire:loading.remove wire:target="confirmInutilizarNfce" class="erp-pcad-actions__label">Inutilizar</span>
                        <span wire:loading wire:target="confirmInutilizarNfce" class="erp-pcad-actions__label">Inutilizando…</span>
                    </button>
                @endif
                <button type="button" wire:click="closeNfceFiscalModal" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon">↩</span>
                    <span class="erp-pcad-actions__label">Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
