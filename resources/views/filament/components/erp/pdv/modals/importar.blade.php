@if ($this->activeModal === 'importar')
    <div class="erp-pdv-modal" role="dialog" aria-label="Importar orçamento">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelImportar"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2>F5 — Importar Orçamento</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-importar-search">Número ou cliente</label>
                <input
                    id="erp-pdv-importar-search"
                    type="text"
                    wire:model.live.debounce.150ms="importarSearch"
                    wire:keydown.enter.prevent="confirmImportarOrcamento"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <div class="erp-pdv-modal__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->importarResults as $index => $row)
                                <tr
                                    wire:click="selectImportarRow({{ $index }})"
                                    wire:dblclick="confirmImportarOrcamento"
                                    wire:key="pdv-importar-{{ $row['orcamento_id'] ?? $index }}"
                                    id="erp-pdv-importar-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->selectedImportarIndex === $index,
                                    ])
                                >
                                    <td>{{ $row['numero'] ?? '—' }}</td>
                                    <td>{{ $row['data'] ?? '' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['cliente'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['total'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">Nenhum orçamento aberto encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmImportarOrcamento" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Importar</button>
                <button type="button" wire:click="cancelImportar" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
