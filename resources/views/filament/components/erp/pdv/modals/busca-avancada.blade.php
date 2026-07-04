@if ($this->activeModal === 'busca_avancada')
    <div class="erp-pdv-modal" role="dialog" aria-label="Busca avançada">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelBuscaAvancada"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
            <header class="erp-pdv-modal__header">
                <h2>F4 — Busca Avançada</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <div class="erp-pdv-busca-avancada__columns">
                    <span class="erp-pdv-modal__label">Pesquisar por:</span>
                    <button
                        type="button"
                        wire:click="setBuscaAvancadaColumn('descricao')"
                        @class(['erp-pdv-busca-avancada__col-btn', 'erp-pdv-busca-avancada__col-btn--active' => $this->buscaAvancadaColumn === 'descricao'])
                    >Descrição</button>
                    <button
                        type="button"
                        wire:click="setBuscaAvancadaColumn('codigo')"
                        @class(['erp-pdv-busca-avancada__col-btn', 'erp-pdv-busca-avancada__col-btn--active' => $this->buscaAvancadaColumn === 'codigo'])
                    >Código</button>
                    <button
                        type="button"
                        wire:click="setBuscaAvancadaColumn('referencia')"
                        @class(['erp-pdv-busca-avancada__col-btn', 'erp-pdv-busca-avancada__col-btn--active' => $this->buscaAvancadaColumn === 'referencia'])
                    >Referência</button>
                    <button
                        type="button"
                        wire:click="setBuscaAvancadaColumn('codigo_barras')"
                        @class(['erp-pdv-busca-avancada__col-btn', 'erp-pdv-busca-avancada__col-btn--active' => $this->buscaAvancadaColumn === 'codigo_barras'])
                    >Código de Barras</button>
                </div>
                <label class="erp-pdv-modal__label" for="erp-pdv-busca-avancada-search">Termo de busca</label>
                <input
                    id="erp-pdv-busca-avancada-search"
                    type="text"
                    wire:model.live.debounce.150ms="buscaAvancadaSearch"
                    wire:keydown.enter.prevent="confirmBuscaAvancada"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                <div class="erp-pdv-modal__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descrição</th>
                                <th>Referência</th>
                                <th>Barras</th>
                                <th class="erp-pdv__grid-col-num">Preço</th>
                                <th class="erp-pdv__grid-col-num">Estoque</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->buscaAvancadaResults as $index => $produto)
                                <tr
                                    wire:click="selectBuscaAvancadaResult({{ $index }})"
                                    wire:dblclick="confirmBuscaAvancada"
                                    wire:key="pdv-busca-avancada-{{ $produto['product_id'] ?? $index }}"
                                    id="erp-pdv-busca-avancada-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--selected' => $this->selectedBuscaAvancadaIndex === $index,
                                    ])
                                >
                                    <td class="erp-pdv__grid-col-codigo">{{ $produto['codigo'] ?? '—' }}</td>
                                    <td class="erp-pdv__grid-col-descricao">{{ $produto['descricao'] ?? '—' }}</td>
                                    <td>{{ $produto['referencia'] ?? '' }}</td>
                                    <td>{{ $produto['codigo_barras'] ?? '' }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ number_format((float) ($produto['preco'] ?? 0), 2, ',', '') }}</td>
                                    <td class="erp-pdv__grid-col-num">
                                        @php $estoque = (float) ($produto['estoque'] ?? 0); @endphp
                                        {{ fmod($estoque, 1.0) === 0.0 ? (int) $estoque : number_format($estoque, 3, ',', '') }}
                                    </td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="6">Informe um termo para pesquisar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmBuscaAvancada" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Selecionar</button>
                <button type="button" wire:click="cancelBuscaAvancada" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
