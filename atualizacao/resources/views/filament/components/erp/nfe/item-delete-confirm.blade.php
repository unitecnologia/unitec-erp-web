@if ($this->nfeItemDeleteConfirmIndex !== null)
    @php
        $item = $this->nfeModalRows[$this->nfeItemDeleteConfirmIndex] ?? null;
        $itemLabel = $item
            ? trim(($item['codigo'] ?? '').' — '.($item['descricao'] ?? ''), ' —')
            : '';
    @endphp
    <div
        class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--warning erp-nfe-item-delete-modal"
        role="alertdialog"
        aria-labelledby="erp-nfe-item-delete-title"
        aria-modal="true"
        wire:keydown.escape.window="cancelDeleteNfeItem"
        wire:keydown.enter.window="confirmDeleteNfeItem"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

            <h2 id="erp-nfe-item-delete-title" class="erp-nfe-fiscal-overlay__title">
                EXCLUIR ITEM
            </h2>

            @if ($itemLabel !== '')
                <p class="erp-nfe-fiscal-overlay__codigo">
                    {{ $itemLabel }}
                </p>
            @endif

            <div class="erp-nfe-fiscal-overlay__text">
                Deseja realmente excluir este item da NF-e?
            </div>

            <div class="erp-nfe-fiscal-overlay__actions">
                <button
                    type="button"
                    wire:click="confirmDeleteNfeItem"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm"
                    id="erp-nfe-item-delete-sim"
                >
                    Sim
                </button>
                <button
                    type="button"
                    wire:click="cancelDeleteNfeItem"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit"
                    id="erp-nfe-item-delete-nao"
                >
                    Não
                </button>
            </div>

            <p class="erp-nfe-fiscal-overlay__hint">Enter confirma · Esc cancela</p>
        </div>
    </div>
@endif
