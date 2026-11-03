@if ($this->nfeAjusteEstornoBuscaOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-list-modal erp-nfe-ajuste-estorno-busca-modal"
        wire:keydown.escape="closeNfeAjusteModals"
        wire:keydown.enter.prevent="confirmarNfeAjusteEstorno"
        wire:keydown.arrow-down.prevent="moveNfeAjusteBuscaSelection(1)"
        wire:keydown.arrow-up.prevent="moveNfeAjusteBuscaSelection(-1)"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeAjusteModals"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-list-modal__window erp-nfe-ajuste-estorno-busca-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-ajuste-estorno-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-ajuste-estorno-title">Estorno — selecionar NF-e de saída autorizada</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeAjusteModals" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-list-modal__body">
                <div class="erp-nfe-import-list">
                    <div class="erp-nfe-import-list__panel erp-nfe-ajuste-estorno-busca-modal__panel">
                        <div class="erp-nfe-ajuste-estorno-busca-modal__filters">
                            <label class="erp-nfe-ajuste-estorno-busca-modal__field erp-nfe-ajuste-estorno-busca-modal__field--periodo" for="erp-nfe-ajuste-data-de">
                                <span class="erp-nfe-import-list__label">Período</span>
                                <div class="erp-nfe-import-list__periodo">
                                    <input
                                        id="erp-nfe-ajuste-data-de"
                                        type="date"
                                        wire:model.live="nfeAjusteBuscaDataDe"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data inicial"
                                    >
                                    <span class="erp-nfe-import-list__periodo-sep">até</span>
                                    <input
                                        id="erp-nfe-ajuste-data-ate"
                                        type="date"
                                        wire:model.live="nfeAjusteBuscaDataAte"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data final"
                                    >
                                </div>
                            </label>

                            <label class="erp-nfe-ajuste-estorno-busca-modal__field erp-nfe-ajuste-estorno-busca-modal__field--cliente" for="erp-nfe-ajuste-cliente">
                                <span class="erp-nfe-import-list__label">Cliente</span>
                                <input
                                    id="erp-nfe-ajuste-cliente"
                                    type="text"
                                    wire:model.live.debounce.250ms="nfeAjusteBuscaCliente"
                                    class="erp-nfe-import-list__input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                    placeholder="Nome do cliente"
                                >
                            </label>

                            <label class="erp-nfe-ajuste-estorno-busca-modal__field erp-nfe-ajuste-estorno-busca-modal__field--chave" for="erp-nfe-ajuste-busca">
                                <span class="erp-nfe-import-list__label">Número ou chave</span>
                                <input
                                    id="erp-nfe-ajuste-busca"
                                    type="text"
                                    wire:model.live.debounce.250ms="nfeAjusteBuscaTermo"
                                    class="erp-nfe-import-list__input"
                                    autocomplete="off"
                                    placeholder="Ex.: 49 ou chave de 44 dígitos"
                                >
                            </label>

                            <div class="erp-nfe-ajuste-estorno-busca-modal__atalhos" role="group" aria-label="Atalhos de período">
                                <button type="button" class="erp-nfe-ajuste-estorno-busca-modal__atalho" wire:click="aplicarPeriodoNfeAjusteMesAtual">Mês atual</button>
                                <button type="button" class="erp-nfe-ajuste-estorno-busca-modal__atalho" wire:click="aplicarPeriodoNfeAjusteMesAnterior">Mês anterior</button>
                                <button type="button" class="erp-nfe-ajuste-estorno-busca-modal__atalho" wire:click="aplicarPeriodoNfeAjusteTodo">Todo o período</button>
                            </div>
                        </div>

                        <div class="erp-nfe-import-list__grid-scroll">
                            <table class="erp-nfe-import-list__grid">
                                <colgroup>
                                    <col class="erp-nfe-import-list__col-numero">
                                    <col class="erp-nfe-import-list__col-data">
                                    <col class="erp-nfe-import-list__col-cliente">
                                    <col class="erp-nfe-import-list__col-total">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th class="erp-nfe-import-list__col-total">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->nfeAjusteBuscaResults as $index => $row)
                                        @php
                                            $isFocused = $this->nfeAjusteBuscaSelectedIndex !== null
                                                && (int) $this->nfeAjusteBuscaSelectedIndex === (int) $index;
                                        @endphp
                                        <tr
                                            wire:click="selectNfeAjusteBuscaRow({{ $index }})"
                                            wire:dblclick="confirmarNfeAjusteEstorno"
                                            wire:key="nfe-ajuste-estorno-{{ $row['id'] ?? $index }}"
                                            @class([
                                                'erp-nfe-import-list__row',
                                                'erp-nfe-import-list__row--focused' => $isFocused,
                                            ])
                                            title="{{ $row['chave'] ?? '' }}"
                                        >
                                            <td>{{ $row['numero'] ?? '—' }}/{{ $row['serie'] ?? '1' }}</td>
                                            <td>{{ $row['data'] ?? '—' }}</td>
                                            <td class="erp-nfe-import-list__cell-cliente" title="{{ $row['cliente'] ?? '—' }}">{{ $row['cliente'] ?? '—' }}</td>
                                            <td class="erp-nfe-import-list__col-total">R$ {{ $row['total'] ?? '0,00' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="erp-nfe-import-list__empty">
                                                Nenhuma NF-e de saída autorizada encontrada.
                                                <span class="erp-nfe-import-list__empty-hint">Ajuste o período, o cliente ou informe o número/chave.</span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="erp-nfe-ajuste-estorno-busca-modal__pager">
                            <span class="erp-nfe-ajuste-estorno-busca-modal__pager-info">
                                {{ $this->nfeAjusteBuscaTotal }} registro(s)
                                @if ($this->nfeAjusteBuscaLastPage > 1)
                                    · página {{ $this->nfeAjusteBuscaPage }} / {{ $this->nfeAjusteBuscaLastPage }}
                                @endif
                            </span>
                            <div class="erp-nfe-ajuste-estorno-busca-modal__pager-actions">
                                <button
                                    type="button"
                                    class="erp-nfe-ajuste-estorno-busca-modal__atalho"
                                    wire:click="irParaPaginaNfeAjusteBusca({{ max(1, $this->nfeAjusteBuscaPage - 1) }})"
                                    @disabled($this->nfeAjusteBuscaPage <= 1)
                                >Anterior</button>
                                <button
                                    type="button"
                                    class="erp-nfe-ajuste-estorno-busca-modal__atalho"
                                    wire:click="irParaPaginaNfeAjusteBusca({{ min($this->nfeAjusteBuscaLastPage, $this->nfeAjusteBuscaPage + 1) }})"
                                    @disabled($this->nfeAjusteBuscaPage >= $this->nfeAjusteBuscaLastPage)
                                >Próxima</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-list-modal__actions">
                <button type="button" wire:click="closeNfeAjusteModals" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Cancelar</span>
                </button>
                <button type="button" wire:click="confirmarNfeAjusteEstorno" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="Enter">
                    <span class="erp-pcad-actions__label"><kbd>ENTER</kbd> | Usar selecionada</span>
                </button>
            </div>
        </div>
    </div>
@endif
