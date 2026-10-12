@if ($this->tabPrecoItemModalOpen)
    <div class="erp-pdv-modal" role="dialog" aria-modal="true" aria-label="Tabela de preço do produto">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelTabPrecoItem"></div>
        <div
            class="erp-pdv-modal__window erp-pdv-modal__window--form erp-pdv-tabela-preco-modal"
            id="erp-os-tab-preco-panel"
            data-selected-index="{{ $this->tabPrecoItemSelectedIndex ?? 0 }}"
        >
            <header class="erp-pdv-modal__header">
                <h2 id="erp-os-tab-preco-title">Tabela de Preço</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">Escolha a tabela para este produto (obrigatório). Use ↑ ↓ e Enter.</p>
                <div class="erp-pdv-tabela-preco-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-pdv-tabela-preco-grid" role="listbox" aria-labelledby="erp-os-tab-preco-title">
                        <thead>
                            <tr>
                                <th style="width: 4.5rem">Cód.</th>
                                <th>Tabela</th>
                                <th class="erp-pdv__grid-col-num" style="width: 7.5rem">Preço</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->tabPrecoItemOpcoes as $index => $op)
                                @php
                                    $selecionado = $this->tabPrecoItemSelectedIndex === $index;
                                    $preco = number_format((float) ($op['preco'] ?? 0), 2, ',', '.');
                                @endphp
                                <tr
                                    wire:key="os-tab-preco-{{ $op['price_table_id'] ?? $index }}"
                                    wire:click="selectTabPrecoItemRow({{ $index }})"
                                    wire:dblclick="confirmTabPrecoItemAt({{ $index }})"
                                    onclick="window.erpOsMoveTabPrecoSelection && window.erpOsMoveTabPrecoSelection(0, {{ $index }})"
                                    id="erp-os-tab-preco-row-{{ $index }}"
                                    role="option"
                                    aria-selected="{{ $selecionado ? 'true' : 'false' }}"
                                    tabindex="{{ $selecionado ? '0' : '-1' }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $selecionado,
                                    ])
                                >
                                    <td>{{ $op['codigo'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $op['descricao'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-num erp-pdv-tabela-preco-preco">R$ {{ $preco }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    id="erp-os-tab-preco-confirm"
                    onclick="window.erpOsConfirmTabPreco && window.erpOsConfirmTabPreco(); return false;"
                >Confirmar</button>
                <button type="button" wire:click="cancelTabPrecoItem" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
