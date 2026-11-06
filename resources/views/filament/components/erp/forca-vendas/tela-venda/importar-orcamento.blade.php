@if ($this->fvImportarOrcamentoOpen)
    <div class="erp-pdv-modal erp-fv-tv-importar" role="dialog" aria-modal="true" aria-labelledby="erp-fv-importar-orc-title">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharImportarOrcamento"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-importar-orc-title">Importar orçamento finalizado</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <div class="erp-fv-tv-importar__filters">
                    <label class="erp-fv-tv-importar__field erp-fv-tv-importar__field--numero" for="erp-fv-importar-orc-numero">
                        <span class="erp-fv-tv-importar__label">Número</span>
                        <input
                            id="erp-fv-importar-orc-numero"
                            type="text"
                            wire:model.live.debounce.150ms="fvImportarOrcNumero"
                            wire:keydown.enter.prevent="confirmarImportarOrcamento"
                            wire:keydown.escape.prevent="fecharImportarOrcamento"
                            wire:keydown.arrow-down.prevent="moveFvImportarOrcamentoSelection(1)"
                            wire:keydown.arrow-up.prevent="moveFvImportarOrcamentoSelection(-1)"
                            class="erp-fv-tv-importar__input"
                            data-erp-uppercase
                            autocomplete="off"
                        >
                    </label>

                    <label class="erp-fv-tv-importar__field erp-fv-tv-importar__field--cliente" for="erp-fv-importar-orc-cliente">
                        <span class="erp-fv-tv-importar__label">Cliente</span>
                        <input
                            id="erp-fv-importar-orc-cliente"
                            type="text"
                            wire:model.live.debounce.150ms="fvImportarOrcCliente"
                            wire:keydown.enter.prevent="confirmarImportarOrcamento"
                            wire:keydown.escape.prevent="fecharImportarOrcamento"
                            wire:keydown.arrow-down.prevent="moveFvImportarOrcamentoSelection(1)"
                            wire:keydown.arrow-up.prevent="moveFvImportarOrcamentoSelection(-1)"
                            class="erp-fv-tv-importar__input"
                            data-erp-uppercase
                            autocomplete="off"
                        >
                    </label>

                    <div class="erp-fv-tv-importar__field erp-fv-tv-importar__field--periodo">
                        <span class="erp-fv-tv-importar__label">Período</span>
                        <div class="erp-fv-tv-importar__periodo">
                            <input
                                id="erp-fv-importar-orc-data-de"
                                type="date"
                                wire:model.change="fvImportarOrcDataDe"
                                data-erp-date
                                data-wire-field="fvImportarOrcDataDe"
                                data-erp-date-wire="iso"
                                data-erp-date-initial="{{ $this->fvImportarOrcDataDe }}"
                                wire:keydown.escape.prevent="fecharImportarOrcamento"
                                class="erp-fv-tv-importar__input erp-fv-tv-importar__input--date"
                                title="Data inicial"
                                aria-label="Data inicial"
                            >
                            <span class="erp-fv-tv-importar__periodo-sep" aria-hidden="true">até</span>
                            <input
                                id="erp-fv-importar-orc-data-ate"
                                type="date"
                                wire:model.change="fvImportarOrcDataAte"
                                data-erp-date
                                data-wire-field="fvImportarOrcDataAte"
                                data-erp-date-wire="iso"
                                data-erp-date-initial="{{ $this->fvImportarOrcDataAte }}"
                                wire:keydown.escape.prevent="fecharImportarOrcamento"
                                class="erp-fv-tv-importar__input erp-fv-tv-importar__input--date"
                                title="Data final"
                                aria-label="Data final"
                            >
                        </div>
                    </div>
                </div>

                <div class="erp-pdv-modal__grid-scroll erp-fv-tv-importar__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-fv-tv-importar__grid">
                        <colgroup>
                            <col class="erp-fv-tv-importar__col-numero">
                            <col class="erp-fv-tv-importar__col-data">
                            <col class="erp-fv-tv-importar__col-cliente">
                            <col class="erp-fv-tv-importar__col-total">
                        </colgroup>
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
                                    <td class="erp-fv-tv-importar__cell-numero">{{ $row['numero'] }}</td>
                                    <td class="erp-fv-tv-importar__cell-data">{{ $row['data'] }}</td>
                                    <td class="erp-fv-tv-importar__cell-cliente" title="{{ $row['cliente'] }}">{{ $row['cliente'] }}</td>
                                    <td class="erp-pdv__grid-col-num erp-fv-tv-importar__cell-total">{{ $row['total'] }}</td>
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
