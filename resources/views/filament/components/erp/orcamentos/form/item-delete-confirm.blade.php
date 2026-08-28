@if ($this->itemDeleteConfirmIndex !== null)
    @php
        $item = $this->itens[$this->itemDeleteConfirmIndex] ?? null;
        $codigo = trim((string) ($item['product_codigo'] ?? ''));
        $descricao = trim((string) ($item['descricao'] ?? ''));
        $qtde = trim((string) ($item['quantidade'] ?? ''));
        $unidade = trim((string) ($item['unidade'] ?? ''));
        $total = trim((string) ($item['total'] ?? ''));
    @endphp
    @teleport('body')
        <div
            class="erp-lookup-modal erp-orc-item-delete-modal"
            wire:keydown.escape="cancelDeleteItem"
            wire:keydown.enter.prevent="confirmDeleteItem"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="cancelDeleteItem"></div>

            <div
                class="erp-orc-item-delete-modal__window"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="erp-orc-item-delete-title"
                aria-describedby="erp-orc-item-delete-desc"
            >
                <button
                    type="button"
                    class="erp-orc-item-delete-modal__close"
                    wire:click="cancelDeleteItem"
                    title="Cancelar"
                    aria-label="Cancelar"
                >✕</button>

                <div class="erp-orc-item-delete-modal__icon" aria-hidden="true">!</div>

                <h2 id="erp-orc-item-delete-title" class="erp-orc-item-delete-modal__title">
                    Excluir este item?
                </h2>

                <p id="erp-orc-item-delete-desc" class="erp-orc-item-delete-modal__lead">
                    Ele será retirado da lista deste orçamento.
                </p>

                @if ($item)
                    <div class="erp-orc-item-delete-modal__card">
                        @if ($codigo !== '')
                            <span class="erp-orc-item-delete-modal__code">{{ $codigo }}</span>
                        @endif
                        @if ($descricao !== '')
                            <p class="erp-orc-item-delete-modal__name">{{ $descricao }}</p>
                        @endif
                        @if ($qtde !== '' || $total !== '')
                            <p class="erp-orc-item-delete-modal__meta">
                                @if ($qtde !== '')
                                    <span>Qtde {{ $qtde }}{{ $unidade !== '' ? ' '.$unidade : '' }}</span>
                                @endif
                                @if ($qtde !== '' && $total !== '')
                                    <span aria-hidden="true">·</span>
                                @endif
                                @if ($total !== '')
                                    <span>Total {{ $total }}</span>
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                <div class="erp-orc-item-delete-modal__actions">
                    <button
                        type="button"
                        wire:click="confirmDeleteItem"
                        class="erp-orc-item-delete-modal__btn erp-orc-item-delete-modal__btn--danger"
                        id="erp-orc-item-delete-sim"
                    >
                        Excluir
                    </button>
                    <button
                        type="button"
                        wire:click="cancelDeleteItem"
                        class="erp-orc-item-delete-modal__btn erp-orc-item-delete-modal__btn--ghost"
                        id="erp-orc-item-delete-nao"
                    >
                        Cancelar
                    </button>
                </div>

                <p class="erp-orc-item-delete-modal__hint">Enter confirma · Esc cancela</p>
            </div>
        </div>
    @endteleport
@endif
