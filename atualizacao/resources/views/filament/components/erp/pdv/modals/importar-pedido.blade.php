@if ($this->activeModal === 'importar_pedido')
    <div class="erp-pdv-modal erp-pdv-modal--centered" role="dialog" aria-labelledby="erp-pdv-importar-pedido-title">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelImportar"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-pdv-modal__window--importar-pedido">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-pdv-importar-pedido-title">Menu Importação</h2>
                <button
                    type="button"
                    class="erp-pdv-modal__close"
                    wire:click="cancelImportarMenu"
                    title="Fechar"
                >✕</button>
            </header>
            <div class="erp-pdv-modal__body erp-pdv-importar-pedido" id="erp-pdv-importar-pedido-panel">
                <p class="erp-pdv-importar-pedido__section-title">Filtrar</p>
                <div class="erp-pdv-importar-pedido__filters">
                    <label class="erp-pdv-importar-pedido__field">
                        <span class="erp-pdv-modal__label">Número</span>
                        <input
                            id="erp-pdv-importar-pedido-numero"
                            type="text"
                            wire:model="importarPedidoNumero"
                            wire:keydown.enter.prevent="refreshImportarPedidoResults"
                            class="erp-pdv-modal__input"
                            data-erp-uppercase
                            autocomplete="off"
                        >
                    </label>
                    <label class="erp-pdv-importar-pedido__field">
                        <span class="erp-pdv-modal__label">Período</span>
                        <div class="erp-pdv-importar-pedido__periodo">
                            <input
                                id="erp-pdv-importar-pedido-de"
                                type="text"
                                wire:model="importarPedidoDe"
                                class="erp-pdv-modal__input"
                                placeholder="dd/mm/aaaa"
                                autocomplete="off"
                            >
                            <span class="erp-pdv-importar-pedido__periodo-sep">até</span>
                            <input
                                id="erp-pdv-importar-pedido-ate"
                                type="text"
                                wire:model="importarPedidoAte"
                                wire:keydown.enter.prevent="refreshImportarPedidoResults"
                                class="erp-pdv-modal__input"
                                placeholder="dd/mm/aaaa"
                                autocomplete="off"
                            >
                        </div>
                    </label>
                    <button
                        type="button"
                        id="erp-pdv-importar-pedido-pesquisar"
                        wire:click="refreshImportarPedidoResults"
                        class="erp-pdv-modal__btn erp-pdv-importar-pedido__search"
                    >
                        <kbd>F9</kbd> Pesquisar
                    </button>
                </div>
                <p class="erp-pdv-importar-pedido__hint">Pedidos cancelados são exibidos em vermelho.</p>
                <div class="erp-pdv-modal__grid-scroll erp-pdv-importar-pedido__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->importarPedidoResults as $index => $row)
                                <tr
                                    wire:click="selectImportarPedidoRow({{ $index }})"
                                    wire:dblclick="confirmImportarPedido"
                                    wire:key="pdv-importar-pedido-{{ $row['venda_id'] ?? $index }}"
                                    id="erp-pdv-importar-pedido-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->selectedImportarPedidoIndex === $index,
                                        'erp-pdv__grid-row--danger' => $row['cancelado'] ?? false,
                                    ])
                                >
                                    <td>{{ $row['numero'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $row['cliente'] ?? '—' }}</td>
                                    <td>{{ $row['data'] ?? '' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['total'] ?? '0,00' }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="4">Nenhum pedido sem documento fiscal emitido.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer erp-pdv-importar-pedido__footer">
                <button
                    type="button"
                    id="erp-pdv-importar-pedido-confirmar"
                    wire:click="confirmImportarPedido"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary erp-pdv-importar-pedido__import-btn"
                >
                    <kbd>F2</kbd> Importar Pedido
                </button>
                <button type="button" wire:click="backImportarMenu" class="erp-pdv-modal__btn">Voltar</button>
                <button type="button" wire:click="cancelImportarMenu" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
