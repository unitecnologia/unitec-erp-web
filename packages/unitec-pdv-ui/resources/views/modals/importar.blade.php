@if ($this->activeModal === 'importar')
    <div class="erp-pdv-modal erp-pdv-importar-orcamento" role="dialog" aria-label="Importar documento">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelImportar"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-pdv-importar-orcamento__window">
            <header class="erp-pdv-modal__header erp-pdv-importar-orcamento__header">
                <h2>{{ $this->importarTitulo }}</h2>
            </header>
            <div class="erp-pdv-modal__body erp-pdv-importar-orcamento__body">
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
                <div class="erp-pdv-modal__grid-scroll erp-pdv-importar-orcamento__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-pdv-importar-orcamento__grid">
                        <colgroup>
                            <col class="erp-pdv-importar-orcamento__col-numero">
                            <col class="erp-pdv-importar-orcamento__col-data">
                            <col class="erp-pdv-importar-orcamento__col-cliente">
                            <col class="erp-pdv-importar-orcamento__col-total">
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
                            @forelse ($this->importarResults as $index => $row)
                                @php($selecionado = (int) ($this->selectedImportarIndex ?? -1) === (int) $index)
                                <tr
                                    wire:click="selectImportarRow({{ $index }})"
                                    wire:dblclick="confirmImportarOrcamento"
                                    wire:key="pdv-importar-{{ $row['orcamento_id'] ?? $index }}"
                                    id="erp-pdv-importar-row-{{ $index }}"
                                    aria-selected="{{ $selecionado ? 'true' : 'false' }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $selecionado,
                                    ])
                                >
                                    <td class="erp-pdv-importar-orcamento__numero">{{ $row['numero'] ?? '—' }}</td>
                                    <td class="erp-pdv-importar-orcamento__data">{{ $row['data'] ?? '' }}</td>
                                    <td class="erp-pdv__grid-col-descricao erp-pdv-importar-orcamento__cliente">{{ $row['cliente'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-num erp-pdv-importar-orcamento__total">{{ $row['total'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">
                                        Nenhum orçamento fechado disponível para importação.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer erp-pdv-importar-orcamento__footer">
                <button type="button" wire:click="confirmImportarOrcamento" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Importar</button>
                <button type="button" wire:click="backImportarMenu" class="erp-pdv-modal__btn">Voltar</button>
                <button type="button" wire:click="cancelImportarMenu" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
