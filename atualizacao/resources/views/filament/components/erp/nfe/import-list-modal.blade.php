@if ($this->nfeImportListOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-list-modal"
        wire:keydown.escape="closeNfeImportList"
        wire:keydown.f5.prevent="confirmNfeImportDocument"
        wire:keydown.arrow-down.prevent="moveNfeImportSelection(1)"
        wire:keydown.arrow-up.prevent="moveNfeImportSelection(-1)"
        wire:keydown.space.prevent="toggleNfeImportMarkFocused"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeImportList"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-list-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-import-list-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-import-list-title">Importar — {{ $this->nfeImportTipoLabel() }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeImportList" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-list-modal__body">
                <div class="erp-nfe-import-list">
                    <div class="erp-nfe-import-list__panel">
                        <div class="erp-nfe-import-list__filters">
                            <label class="erp-nfe-import-list__field erp-nfe-import-list__field--numero" for="erp-nfe-import-numero">
                                <span class="erp-nfe-import-list__label">Número</span>
                                <input
                                    id="erp-nfe-import-numero"
                                    type="text"
                                    wire:model.live.debounce.250ms="nfeImportNumero"
                                    class="erp-nfe-import-list__input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-import-list__field erp-nfe-import-list__field--cliente" for="erp-nfe-import-cliente">
                                <span class="erp-nfe-import-list__label">Cliente</span>
                                <input
                                    id="erp-nfe-import-cliente"
                                    type="text"
                                    wire:model.live.debounce.250ms="nfeImportCliente"
                                    class="erp-nfe-import-list__input"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                            </label>

                            <label class="erp-nfe-import-list__field erp-nfe-import-list__field--periodo">
                                <span class="erp-nfe-import-list__label">Período</span>
                                <div class="erp-nfe-import-list__periodo">
                                    <input
                                        id="erp-nfe-import-data-de"
                                        type="date"
                                        wire:model.live="nfeImportDataDe"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data inicial"
                                    >
                                    <span class="erp-nfe-import-list__periodo-sep">até</span>
                                    <input
                                        id="erp-nfe-import-data-ate"
                                        type="date"
                                        wire:model.live="nfeImportDataAte"
                                        class="erp-nfe-import-list__input erp-nfe-import-list__input--date"
                                        title="Data final"
                                    >
                                </div>
                            </label>
                        </div>

                        <div class="erp-nfe-import-list__grid-scroll">
                            <table class="erp-nfe-import-list__grid">
                                <colgroup>
                                    <col class="erp-nfe-import-list__col-flag">
                                    <col class="erp-nfe-import-list__col-numero">
                                    <col class="erp-nfe-import-list__col-data">
                                    <col class="erp-nfe-import-list__col-cliente">
                                    <col class="erp-nfe-import-list__col-total">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="erp-nfe-import-list__col-flag" aria-label="Marcar"></th>
                                        <th>Número</th>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th class="erp-nfe-import-list__col-total">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->nfeImportResults as $index => $row)
                                        @php
                                            $isMarked = $this->isNfeImportRowMarked((int) $index);
                                            $isFocused = $this->nfeImportSelectedIndex !== null
                                                && (int) $this->nfeImportSelectedIndex === (int) $index;
                                        @endphp
                                        <tr
                                            wire:click="selectNfeImportRow({{ $index }})"
                                            wire:key="nfe-import-row-{{ $row['document_id'] ?? $index }}"
                                            @class([
                                                'erp-nfe-import-list__row',
                                                'erp-nfe-import-list__row--marked' => $isMarked,
                                                'erp-nfe-import-list__row--focused' => $isFocused,
                                            ])
                                        >
                                            <td class="erp-nfe-import-list__col-flag">
                                                <span class="erp-nfe-import-list__checkbox" aria-hidden="true"></span>
                                            </td>
                                            <td>{{ $row['numero'] ?? '—' }}</td>
                                            <td>{{ $row['data'] ?? '—' }}</td>
                                            <td class="erp-nfe-import-list__cell-cliente" title="{{ $row['cliente'] ?? '—' }}">{{ $row['cliente'] ?? '—' }}</td>
                                            <td class="erp-nfe-import-list__col-total">R$ {{ $row['total'] ?? '0,00' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="erp-nfe-import-list__empty">
                                                Nenhum pedido no período.
                                                <span class="erp-nfe-import-list__empty-hint">Altere as datas, o número ou o cliente para buscar.</span>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($this->nfeImportMarkedCount() > 0)
                            <p class="erp-nfe-import-list__marked-hint">
                                {{ $this->nfeImportMarkedCount() }} pedido(s) marcado(s) para importar na mesma NF-e.
                            </p>
                        @endif
                    </div>

                    @if ($this->nfeImportDetalhe)
                        <div class="erp-nfe-import-list__detail">
                            <h3 class="erp-nfe-import-list__detail-title">
                                Documento {{ $this->nfeImportDetalhe['numero'] ?? '—' }}
                            </h3>
                            <p class="erp-nfe-import-list__detail-meta">
                                <span>Cliente: {{ $this->nfeImportDetalhe['cliente'] ?? '—' }}</span>
                                <span>Total: R$ {{ $this->nfeImportDetalhe['total'] ?? '0,00' }}</span>
                            </p>

                            <div class="erp-nfe-import-list__section">
                                <strong>Itens</strong>
                                <div class="erp-nfe-import-list__items-scroll">
                                    <table class="erp-nfe-import-list__items-grid">
                                        <thead>
                                            <tr>
                                                <th>Descrição</th>
                                                <th class="erp-nfe-import-list__col-qtd">Qtd</th>
                                                <th class="erp-nfe-import-list__col-qtd">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->nfeImportDetalhe['itens'] ?? [] as $item)
                                                <tr>
                                                    <td>{{ $item['descricao'] ?? '—' }}</td>
                                                    <td class="erp-nfe-import-list__col-qtd">{{ $item['quantidade'] ?? '0,000' }}</td>
                                                    <td class="erp-nfe-import-list__col-qtd">R$ {{ $item['total'] ?? '0,00' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="erp-nfe-import-list__detail erp-nfe-import-list__detail--empty">
                            <p>Selecione um ou mais pedidos para visualizar e importar.</p>
                            <p class="erp-nfe-import-list__detail-tip">Clique para marcar · Espaço marca o foco · F5 importa todos os marcados.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-list-modal__actions">
                <button type="button" wire:click="confirmNfeImportDocument" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">↓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Importar</span>
                </button>
                <button type="button" wire:click="closeNfeImportList" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Voltar</span>
                </button>
            </div>
        </div>
    </div>
@endif
