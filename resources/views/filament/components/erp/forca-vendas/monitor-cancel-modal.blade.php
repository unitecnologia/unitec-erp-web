@if ($this->cancelarPedidosModalOpen)
    @php
        $motivoLength = mb_strlen(trim($this->cancelarPedidosMotivo), 'UTF-8');
        $minMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MIN_LENGTH_MONITOR_FV;
        $maxMotivo = \App\Support\Erp\Pdv\PdvEstornoMotivo::MAX_LENGTH;
        $qtd = max(0, (int) $this->cancelarPedidosQuantidade);
    @endphp
    <div
        class="erp-vendas-cancel-modal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-fv-mon-cancel-motivo')?.focus())"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeCancelarPedidosModal(); }
            if ($event.key === 'Enter' && $event.ctrlKey) { $event.preventDefault(); $wire.confirmCancelarSelecionados(); }
        "
    >
        <div class="erp-vendas-cancel-modal__backdrop" wire:click="closeCancelarPedidosModal"></div>

        <div class="erp-vendas-cancel-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-fv-mon-cancel-title">
            <div class="erp-vendas-cancel-modal__titlebar">
                <span id="erp-fv-mon-cancel-title">Cancelar pedido{{ $qtd === 1 ? '' : 's' }}</span>
                <button
                    type="button"
                    class="erp-vendas-cancel-modal__close"
                    wire:click="closeCancelarPedidosModal"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-vendas-cancel-modal__body">
                <p class="erp-vendas-cancel-modal__hint">
                    {{ $qtd }} pedido{{ $qtd === 1 ? '' : 's' }} selecionado{{ $qtd === 1 ? '' : 's' }} —
                    informe o motivo do cancelamento.
                </p>

                <label class="erp-vendas-cancel-modal__label" for="erp-fv-mon-cancel-motivo">Motivo do cancelamento</label>
                <textarea
                    id="erp-fv-mon-cancel-motivo"
                    wire:model.live.debounce.150ms="cancelarPedidosMotivo"
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
                    wire:click="confirmCancelarSelecionados"
                >Confirmar cancelamento</button>
                <button
                    type="button"
                    class="erp-vendas-cancel-modal__btn"
                    wire:click="closeCancelarPedidosModal"
                >Voltar</button>
            </div>
        </div>
    </div>
@endif
