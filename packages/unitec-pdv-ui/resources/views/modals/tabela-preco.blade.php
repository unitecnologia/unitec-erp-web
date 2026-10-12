@if ($this->activeModal === 'tabela_preco')
    <div class="erp-pdv-modal" role="dialog" aria-label="Tabela de preço" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelTabelaPreco"></div>
        <div
            class="erp-pdv-modal__window erp-pdv-modal__window--form erp-pdv-tabela-preco-modal"
            id="erp-pdv-tabela-preco-panel"
            data-selected-index="{{ $this->selectedTabelaPrecoIndex ?? 0 }}"
        >
            <header class="erp-pdv-modal__header">
                <h2 id="erp-pdv-tabela-preco-title">Tabela de Preço</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">
                    @if ($this->pdvTabelaPrecoItemMode)
                        Escolha a tabela para este produto (obrigatório). Use ↑ ↓ e Enter.
                    @else
                        Selecione a tabela ativa para precificação no PDV. Use ↑ ↓ e Enter.
                    @endif
                </p>
                <div class="erp-pdv-tabela-preco-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-pdv-tabela-preco-grid" role="listbox" aria-labelledby="erp-pdv-tabela-preco-title">
                        <thead>
                            <tr>
                                <th style="width: 4.5rem">Cód.</th>
                                <th>Tabela</th>
                                <th class="erp-pdv__grid-col-num" style="width: 7.5rem">Preço</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->tabelaPrecoResults as $index => $row)
                                @php
                                    $selecionado = $this->selectedTabelaPrecoIndex === $index;
                                    $preco = isset($row['preco']) ? number_format((float) $row['preco'], 2, ',', '.') : null;
                                @endphp
                                <tr
                                    wire:key="pdv-tab-preco-{{ $row['price_table_id'] ?? $index }}"
                                    wire:click="selectTabelaPrecoRow({{ $index }})"
                                    wire:dblclick="confirmTabelaPrecoAt({{ $index }})"
                                    onclick="(function(i){const p=document.getElementById('erp-pdv-tabela-preco-panel');if(!p)return;p.dataset.selectedIndex=String(i);p.querySelectorAll('[id^=erp-pdv-tabela-preco-row-]').forEach(function(r,idx){var on=idx===i;r.classList.toggle('erp-pdv__grid-row--selected',on);r.setAttribute('aria-selected',on?'true':'false');});})({{ $index }})"
                                    id="erp-pdv-tabela-preco-row-{{ $index }}"
                                    role="option"
                                    aria-selected="{{ $selecionado ? 'true' : 'false' }}"
                                    tabindex="{{ $selecionado ? '0' : '-1' }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $selecionado,
                                    ])
                                >
                                    <td>{{ $row['codigo'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['descricao'] ?? ($row['label'] ?? '—') }}</td>
                                    <td class="erp-pdv__grid-col-num erp-pdv-tabela-preco-preco">
                                        @if ($preco !== null)
                                            R$ {{ $preco }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="3">Nenhuma tabela disponível.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    id="erp-pdv-tabela-preco-confirm"
                    onclick="window.confirmPdvTabelaPrecoFromUi && window.confirmPdvTabelaPrecoFromUi(); return false;"
                >Confirmar</button>
                <button type="button" wire:click="cancelTabelaPreco" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
