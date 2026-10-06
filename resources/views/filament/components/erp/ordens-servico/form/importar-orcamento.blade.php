@if ($this->osImportOrcamentoOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-list-modal erp-os-import-orc-modal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-os-import-orc-numero')?.focus())"
        wire:keydown.escape.window="fecharImportarOrcamentoOs"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharImportarOrcamentoOs"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-list-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-os-import-orc-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-import-orc-title">Importar orçamento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharImportarOrcamentoOs" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-list-modal__body">
                <div class="erp-nfe-import-list">
                    <div class="erp-nfe-import-list__panel">
                        <div class="erp-nfe-import-list__filters">
                            <label class="erp-nfe-import-list__field" for="erp-os-import-orc-numero">
                                <span class="erp-nfe-import-list__label">Número</span>
                                <input
                                    id="erp-os-import-orc-numero"
                                    type="text"
                                    wire:model.live.debounce.250ms="osImportOrcNumero"
                                    class="erp-nfe-import-list__input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-import-list__field" for="erp-os-import-orc-cliente">
                                <span class="erp-nfe-import-list__label">Cliente</span>
                                <input
                                    id="erp-os-import-orc-cliente"
                                    type="text"
                                    wire:model.live.debounce.250ms="osImportOrcCliente"
                                    class="erp-nfe-import-list__input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-import-list__field">
                                <span class="erp-nfe-import-list__label">Período</span>
                                <div class="erp-nfe-import-list__periodo">
                                    <input
                                        id="erp-os-import-orc-data-de"
                                        type="date"
                                        wire:model.live="osImportOrcDataDe"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data inicial"
                                    >
                                    <span class="erp-nfe-import-list__periodo-sep">até</span>
                                    <input
                                        id="erp-os-import-orc-data-ate"
                                        type="date"
                                        wire:model.live="osImportOrcDataAte"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data final"
                                    >
                                </div>
                            </label>
                        </div>

                        <div class="erp-nfe-import-list__grid-scroll">
                            <table class="erp-nfe-import-list__grid">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th class="erp-os-import-orc-modal__col-total">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->osImportOrcamentoResults as $index => $row)
                                        <tr
                                            wire:click="selectOsImportOrcamentoRow({{ $index }})"
                                            wire:dblclick="confirmarImportarOrcamentoOs"
                                            wire:key="os-import-orc-{{ $row['orcamento_id'] }}"
                                            @class([
                                                'erp-nfe-import-list__row',
                                                'erp-nfe-import-list__row--focused' => $this->osImportOrcamentoSelectedIndex === $index,
                                            ])
                                        >
                                            <td>{{ $row['numero'] }}</td>
                                            <td>{{ $row['data'] }}</td>
                                            <td>{{ $row['cliente'] }}</td>
                                            <td class="erp-os-import-orc-modal__col-total">{{ $row['total'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Nenhum orçamento válido para importação.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-list-modal__actions">
                <button type="button" wire:click="confirmarImportarOrcamentoOs" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">↓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Importar</span>
                </button>
                <button type="button" wire:click="fecharImportarOrcamentoOs" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
