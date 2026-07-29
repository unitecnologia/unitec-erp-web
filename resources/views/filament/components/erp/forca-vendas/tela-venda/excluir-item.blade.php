@if ($this->excluirItemModalOpen)
    @php
        $item = ($this->itemSelecionado !== null && isset($this->itens[$this->itemSelecionado]))
            ? $this->itens[$this->itemSelecionado]
            : null;
        $descricao = is_array($item) ? (string) ($item['descricao'] ?? '') : '';
    @endphp
    <div class="erp-pdv-modal erp-fv-tv-excluir" role="dialog" aria-modal="true" aria-labelledby="erp-fv-excluir-title">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelarExcluirItem"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-excluir-title">Confirmação</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__confirm-text">Deseja excluir o item?</p>
                @if ($descricao !== '')
                    <p class="erp-pdv-modal__hint">{{ $descricao }}</p>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    id="erp-fv-excluir-sim"
                    wire:click="confirmarExcluirItem"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                >Sim</button>
                <button
                    type="button"
                    id="erp-fv-excluir-nao"
                    wire:click="cancelarExcluirItem"
                    class="erp-pdv-modal__btn"
                >Não</button>
            </footer>
        </div>
    </div>
@endif
