@php
    use App\Models\DevolucaoVenda;
    $travada = $this->devolucaoTravada;
@endphp

<div class="erp-devvenda-shell">
    <section class="erp-devvenda-panel">
        <h3 class="erp-devvenda-panel__title">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm7 1.5V9h4.5"/></svg>
            Dados da Devolução
        </h3>

        <div class="erp-devvenda-grid erp-devvenda-grid--linha1">
            <label class="erp-devvenda-field erp-devvenda-field--numero">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v14l-4-2-3 2-3-2-4 2V5a2 2 0 0 1 2-2z"/></svg>
                    Número
                </span>
                <input type="text" readonly value="{{ $this->numeroDisplay }}" class="erp-devvenda-input">
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--venda">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4zM7 9v6M10 9v6M13 9v6"/></svg>
                    Venda Origem
                </span>
                <div class="erp-devvenda-lookup">
                    <input
                        id="erp-devvenda-venda"
                        type="text"
                        wire:model.live.debounce.250ms="vendaSearch"
                        wire:focus="openVendaLookup"
                        wire:keydown.arrow-up.prevent="moveVendaSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveVendaSelection(1)"
                        wire:keydown.enter.prevent="handleVendaEnter"
                        wire:keydown.escape.prevent="closeVendaLookup"
                        class="erp-devvenda-input erp-devvenda-input--lookup"
                        placeholder="Número, cliente ou data"
                        autocomplete="off"
                        @disabled($travada)
                    >
                    <button type="button" class="erp-devvenda-lookup__btn" tabindex="-1" wire:click="buscarVendaPelaData" title="Buscar pela data" aria-label="Buscar venda pela data" @disabled($travada)>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/></svg>
                    </button>
                    @if ($this->vendaLookupOpen)
                        <div class="erp-devvenda-lookup__list">
                            @forelse ($this->vendaResults as $index => $row)
                                <button
                                    type="button"
                                    wire:click="selectVenda({{ $row['id'] }})"
                                    @class([
                                        'erp-devvenda-lookup__item',
                                        'is-active' => $this->selectedVendaIndex === $index,
                                    ])
                                >
                                    <strong>{{ $row['numero'] }}</strong>
                                    <span>{{ $row['data'] }} · {{ $row['cliente'] }} · R$ {{ $row['total'] }}</span>
                                </button>
                            @empty
                                <div class="erp-devvenda-lookup__empty">Nenhuma venda encontrada.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--data">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                    Data
                </span>
                <input type="date" wire:model="dataDevolucao" wire:change="atualizarDataDevolucao" class="erp-devvenda-input" title="Se não souber o número, busque a venda por esta data" @disabled($travada)>
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--hora">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
                    Hora
                </span>
                <input type="time" wire:model="horaDevolucao" class="erp-devvenda-input" @disabled($travada)>
            </label>
        </div>

        <div class="erp-devvenda-grid erp-devvenda-grid--linha2">
            <label class="erp-devvenda-field erp-devvenda-field--cliente">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 19c1.5-3 4-4.5 7-4.5S17.5 16 19 19"/></svg>
                    Cliente
                </span>
                <input type="text" readonly wire:model="clienteNome" class="erp-devvenda-input erp-devvenda-input--readonly">
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--vendedor">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M6 19v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/></svg>
                    Vendedor
                </span>
                <input type="text" readonly wire:model="vendedorNome" class="erp-devvenda-input erp-devvenda-input--readonly" placeholder="—">
            </label>
        </div>

        <div class="erp-devvenda-grid erp-devvenda-grid--linha3">
            <label class="erp-devvenda-field erp-devvenda-field--tipo">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                    Tipo
                </span>
                <select wire:model="tipoDevolucao" class="erp-devvenda-input" @disabled($travada)>
                    @foreach (DevolucaoVenda::tipoLabels() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--obs">
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h4"/></svg>
                    Observações
                </span>
                <input type="text" maxlength="250" wire:model="observacoes" class="erp-devvenda-input" placeholder="Motivo / observação" @disabled($travada)>
            </label>
        </div>
    </section>

    <section class="erp-devvenda-panel erp-devvenda-panel--itens">
        <h3 class="erp-devvenda-panel__title">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7V5a4 4 0 0 1 8 0v2M5 7h14l-1 13H6L5 7z"/></svg>
            Itens a Devolver
        </h3>

        <div class="erp-devvenda-itens__table-wrap">
            <table class="erp-devvenda-itens__table">
                <thead>
                    <tr>
                        <th class="col-cod">Código</th>
                        <th class="col-desc">Produto</th>
                        <th class="col-qty">Qtd vendida</th>
                        <th class="col-qty col-edit">Qtd devolução</th>
                        <th class="col-price">Preço</th>
                        <th class="col-total">Total</th>
                        <th class="col-act"><span class="erp-devvenda-itens__sr">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->itens as $index => $item)
                        <tr
                            wire:key="{{ $item['key'] ?? $index }}"
                            wire:click="selectItem({{ $index }})"
                            @class(['is-selected' => $this->selectedItemIndex === $index])
                        >
                            <td class="col-cod" title="{{ $item['produto_codigo'] ?: '' }}">{{ $item['produto_codigo'] ?: '—' }}</td>
                            <td class="col-desc" title="{{ $item['produto_descricao'] ?: '' }}">
                                <span>{{ $item['produto_descricao'] ?: '—' }}</span>
                            </td>
                            <td class="col-qty"><span class="erp-devvenda-itens__static">{{ $item['qtd_vendida'] }}</span></td>
                            <td class="col-qty col-edit">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    wire:model.live.debounce.300ms="itens.{{ $index }}.qtd"
                                    class="erp-devvenda-input erp-devvenda-input--cell"
                                    aria-label="Quantidade da devolução"
                                    @disabled($travada)
                                >
                            </td>
                            <td class="col-price">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    wire:model.live.debounce.300ms="itens.{{ $index }}.preco"
                                    class="erp-devvenda-input erp-devvenda-input--cell"
                                    aria-label="Preço da devolução"
                                    @disabled($travada)
                                >
                            </td>
                            <td class="col-total">{{ $item['total'] }}</td>
                            <td class="col-act">
                                <button
                                    type="button"
                                    class="erp-devvenda-itens__remove"
                                    wire:click.stop="removeItemAt({{ $index }})"
                                    title="Remover"
                                    aria-label="Remover item"
                                    @disabled($travada)
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 7h16"/>
                                        <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/>
                                        <path d="M7 7l.8 12.2A1.5 1.5 0 0 0 9.3 20.5h5.4a1.5 1.5 0 0 0 1.5-1.3L17 7"/>
                                        <path d="M10 11v6M14 11v6"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="erp-devvenda-itens__empty">
                                Selecione uma venda para carregar os itens.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="erp-devvenda-total">
            <span>Σ Total da Devolução</span>
            <strong>R$ {{ $this->totalDisplay }}</strong>
        </div>
    </section>
</div>
