@if ($this->activeModal === 'receber')
    <div class="erp-pdv-modal" role="dialog" aria-label="Receber contas">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelReceber"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+R — Receber</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-receber-search">Número, cliente ou documento</label>
                <input
                    id="erp-pdv-receber-search"
                    type="text"
                    wire:model.live.debounce.150ms="receberSearch"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <div class="erp-pdv-modal__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Vencimento</th>
                                <th class="erp-pdv__grid-col-num">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->receberResults as $index => $row)
                                <tr
                                    wire:click="selectReceberRow({{ $index }})"
                                    wire:key="pdv-receber-{{ $row['conta_id'] ?? $index }}"
                                    id="erp-pdv-receber-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->selectedReceberIndex === $index,
                                    ])
                                >
                                    <td>{{ $row['numero'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['cliente'] ?? '—' }}</td>
                                    <td>{{ $row['vencimento'] ?? '' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['saldo_fmt'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">Nenhuma conta com saldo em aberto.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <label class="erp-pdv-modal__label" for="erp-pdv-receber-valor">Valor a receber</label>
                <input
                    id="erp-pdv-receber-valor"
                    type="text"
                    wire:model="receberValor"
                    wire:keydown.enter.prevent="confirmReceberConta"
                    class="erp-pdv-modal__input"
                    data-mask="money"
                    autocomplete="off"
                >
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmReceberConta" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Confirmar</button>
                <button type="button" wire:click="cancelReceber" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
