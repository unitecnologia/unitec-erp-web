@if ($this->fvImportarOrcamentoOpen)
    <div class="erp-pdv-modal erp-fv-tv-importar" role="dialog" aria-modal="true" aria-labelledby="erp-fv-importar-orc-title">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharImportarOrcamento"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-importar-orc-title">Importar orçamento finalizado</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-fv-importar-orc-search">Número ou cliente</label>
                <input
                    id="erp-fv-importar-orc-search"
                    type="text"
                    wire:model.live.debounce.150ms="fvImportarOrcamentoSearch"
                    wire:keydown.enter.prevent="confirmarImportarOrcamento"
                    wire:keydown.escape.prevent="fecharImportarOrcamento"
                    wire:keydown.arrow-down.prevent="moveFvImportarOrcamentoSelection(1)"
                    wire:keydown.arrow-up.prevent="moveFvImportarOrcamentoSelection(-1)"
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
                            @forelse ($this->fvImportarOrcamentoResults as $index => $row)
                                <tr
                                    wire:click="selectFvImportarOrcamentoRow({{ $index }})"
                                    wire:dblclick="confirmarImportarOrcamento"
                                    wire:key="fv-importar-orc-{{ $row['orcamento_id'] }}"
                                    id="erp-fv-importar-orc-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->fvImportarOrcamentoSelectedIndex === $index,
                                    ])
                                >
                                    <td>{{ $row['numero'] }}</td>
                                    <td>{{ $row['data'] }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['cliente'] }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">Nenhum orçamento finalizado encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmarImportarOrcamento" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Importar</button>
                <button type="button" wire:click="fecharImportarOrcamento" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
