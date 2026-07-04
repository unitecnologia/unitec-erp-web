@if ($this->activeModal === 'remover_itens')
    <div class="erp-pdv-modal" role="dialog" aria-label="Remover itens">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelRemoverItens"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>F11 — Remover Itens</h2>
            </header>
            <div class="erp-pdv-modal__body">
                @if ($this->cupomItemSelecionado)
                    <p class="erp-pdv-modal__hint">{{ $this->cupomItemSelecionado['descricao'] ?? '' }}</p>
                    <p class="erp-pdv-modal__hint">
                        Quantidade atual:
                        {{ $this->formatCupomQuantidade((float) ($this->cupomItemSelecionado['quantidade'] ?? 0)) }}
                        {{ $this->cupomItemSelecionado['unidade'] ?? 'UN' }}
                    </p>
                @endif
                <label class="erp-pdv-modal__label" for="erp-pdv-remover-itens-qtd">Quantidade a remover</label>
                <input
                    id="erp-pdv-remover-itens-qtd"
                    type="text"
                    wire:model="removerItensQtd"
                    wire:keydown.enter.prevent="confirmRemoverItens"
                    class="erp-pdv-modal__input"
                    data-mask="quantity3"
                    autocomplete="off"
                >
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmRemoverItens" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Confirmar</button>
                <button type="button" wire:click="cancelRemoverItens" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
