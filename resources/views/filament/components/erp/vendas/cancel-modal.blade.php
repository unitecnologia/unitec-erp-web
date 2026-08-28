@if ($this->cancelVendaModalOpen)
    @php
        $motivoLength = mb_strlen(trim($this->cancelVendaMotivo), 'UTF-8');
        $minMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MIN_LENGTH;
        $maxMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MAX_LENGTH;
    @endphp
    <div
        class="erp-vendas-cancel-modal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-vendas-cancel-motivo')?.focus())"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeCancelVendaModal(); }
            if ($event.key === 'Enter' && $event.ctrlKey) { $event.preventDefault(); $wire.confirmCancelVenda(); }
        "
    >
        <div class="erp-vendas-cancel-modal__backdrop" wire:click="closeCancelVendaModal"></div>

        <div class="erp-vendas-cancel-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-vendas-cancel-title">
            <div class="erp-vendas-cancel-modal__titlebar">
                <span id="erp-vendas-cancel-title">Cancelar venda</span>
                <button
                    type="button"
                    class="erp-vendas-cancel-modal__close"
                    wire:click="closeCancelVendaModal"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-vendas-cancel-modal__body">
                <p class="erp-vendas-cancel-modal__hint">
                    Venda #{{ $this->cancelVendaNumero ?? '—' }} — informe o motivo do cancelamento.
                </p>

                <label class="erp-vendas-cancel-modal__label" for="erp-vendas-cancel-motivo">Motivo do cancelamento</label>
                <textarea
                    id="erp-vendas-cancel-motivo"
                    wire:model.live.debounce.150ms="cancelVendaMotivo"
                    class="erp-vendas-cancel-modal__textarea"
                    rows="4"
                    maxlength="{{ $maxMotivo }}"
                    placeholder="Descreva o motivo do cancelamento (mínimo {{ $minMotivo }} caracteres)"
                    autocomplete="off"
                ></textarea>

                <p @class([
                    'erp-vendas-cancel-modal__counter',
                    'erp-vendas-cancel-modal__counter--ok' => $motivoLength >= $minMotivo,
                    'erp-vendas-cancel-modal__counter--warn' => $motivoLength > 0 && $motivoLength < $minMotivo,
                ])>
                    {{ $motivoLength }}/{{ $maxMotivo }}
                    @if ($motivoLength < $minMotivo)
                        — faltam {{ $minMotivo - $motivoLength }} caracteres
                    @endif
                </p>
            </div>

            <div class="erp-vendas-cancel-modal__actions">
                <button
                    type="button"
                    class="erp-vendas-cancel-modal__btn erp-vendas-cancel-modal__btn--danger"
                    wire:click="confirmCancelVenda"
                >Confirmar cancelamento</button>
                <button
                    type="button"
                    class="erp-vendas-cancel-modal__btn"
                    wire:click="closeCancelVendaModal"
                >Voltar</button>
            </div>
        </div>
    </div>
@endif
