@if ($this->activeModal === 'reimprimir')
    <div class="erp-pdv-modal" role="dialog" aria-label="Reimprimir pedido">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelReimprimir"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+P — Reimprimir Pedido</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-reimprimir-search">Número ou vendedor</label>
                <input
                    id="erp-pdv-reimprimir-search"
                    type="text"
                    wire:model.live.debounce.150ms="reimprimirSearch"
                    wire:keydown.enter.prevent="confirmReimprimir"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <div class="erp-pdv-modal__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Vendedor</th>
                                <th>Forma</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->reimprimirResults as $index => $row)
                                <tr
                                    wire:click="selectReimprimirRow({{ $index }})"
                                    wire:dblclick="confirmReimprimir"
                                    wire:key="pdv-reimprimir-{{ $row['venda_id'] ?? $index }}"
                                    id="erp-pdv-reimprimir-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->selectedReimprimirIndex === $index,
                                    ])
                                >
                                    <td>{{ $row['numero'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['vendedor'] ?? '—' }}</td>
                                    <td>{{ $row['forma'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['total'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">Nenhuma venda neste caixa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmReimprimir" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Imprimir</button>
                <button type="button" wire:click="cancelReimprimir" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
