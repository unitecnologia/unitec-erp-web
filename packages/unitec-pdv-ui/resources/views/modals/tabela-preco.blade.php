@if ($this->activeModal === 'tabela_preco')
    <div class="erp-pdv-modal" role="dialog" aria-label="Tabela de preço">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelTabelaPreco"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>Tabela de Preço</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">Selecione a tabela ativa para precificação no PDV.</p>
                <ul class="erp-pdv-options__list erp-pdv-tabela-preco-list">
                    @forelse ($this->tabelaPrecoResults as $index => $row)
                        <li>
                            <button
                                type="button"
                                wire:click="selectTabelaPrecoRow({{ $index }})"
                                wire:dblclick="confirmTabelaPreco"
                                id="erp-pdv-tabela-preco-row-{{ $index }}"
                                @class(['erp-pdv-vendedor-row', 'erp-pdv-vendedor-row--selected' => $this->selectedTabelaPrecoIndex === $index])
                            >
                                {{ $row['codigo'] ?? '' }} — {{ $row['descricao'] ?? '' }}
                            </button>
                        </li>
                    @empty
                        <li class="erp-pdv-modal__hint">Nenhuma tabela cadastrada.</li>
                    @endforelse
                </ul>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmTabelaPreco" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" id="erp-pdv-tabela-preco-confirm">Confirmar</button>
                <button type="button" wire:click="cancelTabelaPreco" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
