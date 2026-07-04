@if ($this->itemDeleteConfirmIndex !== null)
    @teleport('body')
        <div
            class="erp-lookup-modal erp-orc-item-delete-modal"
            wire:keydown.escape="cancelDeleteItem"
            wire:keydown.enter.prevent="confirmDeleteItem"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteItem"></div>

            <div class="erp-lookup-modal__window erp-orc-item-delete-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-item-delete-title">
                <div class="erp-lookup-modal__titlebar">
                    <span id="erp-orc-item-delete-title">Excluir item</span>
                    <button
                        type="button"
                        class="erp-lookup-modal__close"
                        wire:click="cancelDeleteItem"
                        title="Não"
                    >✕</button>
                </div>

                <div class="erp-lookup-modal__body erp-orc-item-delete-modal__body">
                    <p class="erp-orc-item-delete-modal__message">
                        Deseja realmente excluir este item do orçamento?
                    </p>
                    @if (isset($this->itens[$this->itemDeleteConfirmIndex]))
                        <p class="erp-orc-item-delete-modal__item">
                            {{ $this->itens[$this->itemDeleteConfirmIndex]['product_codigo'] ?? '' }}
                            —
                            {{ $this->itens[$this->itemDeleteConfirmIndex]['descricao'] ?? '' }}
                        </p>
                    @endif
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-item-delete-modal__actions">
                    <button type="button" wire:click="confirmDeleteItem" class="erp-pcad-actions__btn" id="erp-orc-item-delete-sim">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label">Sim</span>
                    </button>
                    <button type="button" wire:click="cancelDeleteItem" class="erp-pcad-actions__btn" id="erp-orc-item-delete-nao">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label">Não</span>
                    </button>
                </div>
            </div>
        </div>
    @endteleport
@endif
