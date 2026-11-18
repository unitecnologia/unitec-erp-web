@if ($this->activeModal === 'reimprimir_nfce')
    <div class="erp-pdv-modal" role="dialog" aria-label="Reimprimir NFC-e">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelReimprimirNfce"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+I — Reimprimir NFC-e</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-reimprimir-nfce-search">Número da venda, NFC-e ou vendedor</label>
                <input
                    id="erp-pdv-reimprimir-nfce-search"
                    type="text"
                    wire:model.live.debounce.150ms="reimprimirNfceSearch"
                    wire:keydown.enter.prevent="confirmReimprimirNfce"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <div class="erp-pdv-modal__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-pdv-reimprimir-nfce-grid">
                        <thead>
                            <tr>
                                <th>Venda</th>
                                <th>NFC-e</th>
                                <th class="erp-pdv-reimprimir-nfce-grid__status">Status</th>
                                <th>Vendedor</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->reimprimirNfceResults as $index => $row)
                                <tr
                                    wire:click="selectReimprimirNfceRow({{ $index }})"
                                    wire:dblclick="confirmReimprimirNfce"
                                    wire:key="pdv-reimprimir-nfce-{{ $row['venda_id'] ?? $index }}"
                                    id="erp-pdv-reimprimir-nfce-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => (int) ($this->selectedReimprimirNfceIndex ?? -1) === (int) $index,
                                    ])
                                >
                                    <td>{{ $row['numero'] ?? '—' }}</td>
                                    <td>{{ $row['nfce_numero'] ?? '—' }}</td>
                                    <td class="erp-pdv-reimprimir-nfce-grid__status">{{ mb_strtoupper((string) ($row['status'] ?? '—'), 'UTF-8') }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['vendedor'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['total'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="5">Nenhuma NFC-e autorizada/contingência neste caixa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmReimprimirNfce" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Reimprimir</button>
                <button type="button" wire:click="cancelReimprimirNfce" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
