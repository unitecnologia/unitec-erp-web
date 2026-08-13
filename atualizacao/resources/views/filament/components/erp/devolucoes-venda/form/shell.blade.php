@php
    use App\Models\DevolucaoVenda;
@endphp

<div class="erp-devvenda-shell">
    <section class="erp-devvenda-panel">
        <h3 class="erp-devvenda-panel__title">Dados da devolução</h3>

        <div class="erp-devvenda-grid">
            <label class="erp-devvenda-field">
                <span>Número</span>
                <input type="text" readonly value="{{ $this->numeroDisplay }}" class="erp-devvenda-input erp-devvenda-input--sm">
            </label>

            <label class="erp-devvenda-field erp-devvenda-field--grow">
                <span>Venda origem</span>
                <div class="erp-devvenda-lookup">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="vendaSearch"
                        wire:focus="openVendaLookup"
                        wire:keydown.arrow-up.prevent="moveVendaSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveVendaSelection(1)"
                        wire:keydown.enter.prevent="handleVendaEnter"
                        wire:keydown.escape.prevent="closeVendaLookup"
                        class="erp-devvenda-input"
                        placeholder="Número da venda ou cliente"
                        autocomplete="off"
                    >
                    @if ($this->vendaLookupOpen && filled($this->vendaSearch))
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

            <label class="erp-devvenda-field">
                <span>Data</span>
                <input type="date" wire:model="dataDevolucao" class="erp-devvenda-input erp-devvenda-input--sm">
            </label>

            <label class="erp-devvenda-field">
                <span>Hora</span>
                <input type="time" wire:model="horaDevolucao" class="erp-devvenda-input erp-devvenda-input--sm">
            </label>
        </div>

        <div class="erp-devvenda-grid">
            <label class="erp-devvenda-field erp-devvenda-field--grow">
                <span>Cliente</span>
                <input type="text" readonly wire:model="clienteNome" class="erp-devvenda-input erp-devvenda-input--readonly">
            </label>

            <label class="erp-devvenda-field">
                <span>Vendedor</span>
                <select wire:model="vendedorId" class="erp-devvenda-input">
                    <option value="">—</option>
                    @foreach ($this->vendedorOptions() as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['nome'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-devvenda-field">
                <span>Tipo</span>
                <select wire:model="tipoDevolucao" class="erp-devvenda-input">
                    @foreach (DevolucaoVenda::tipoLabels() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="erp-devvenda-field erp-devvenda-field--full">
            <span>Observações</span>
            <input type="text" maxlength="250" wire:model="observacoes" class="erp-devvenda-input" placeholder="Motivo / observação">
        </label>
    </section>

    <section class="erp-devvenda-panel erp-devvenda-panel--itens">
        <div class="erp-devvenda-itens__head">
            <h3 class="erp-devvenda-panel__title">Itens a devolver</h3>
            <button type="button" class="erp-devvenda-btn-link" wire:click="removeSelectedItem" @disabled($this->selectedItemIndex === null)>
                Remover item
            </button>
        </div>

        <div class="erp-devvenda-itens__table-wrap">
            <table class="erp-devvenda-itens__table">
                <thead>
                    <tr>
                        <th class="col-cod">Código</th>
                        <th class="col-desc">Produto</th>
                        <th class="col-qty">Qtd vendida</th>
                        <th class="col-qty">Qtd devolução</th>
                        <th class="col-price">Preço</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->itens as $index => $item)
                        <tr
                            wire:key="{{ $item['key'] ?? $index }}"
                            wire:click="selectItem({{ $index }})"
                            @class(['is-selected' => $this->selectedItemIndex === $index])
                        >
                            <td class="col-cod">{{ $item['produto_codigo'] ?: '—' }}</td>
                            <td class="col-desc">{{ $item['produto_descricao'] ?: '—' }}</td>
                            <td class="col-qty">{{ $item['qtd_vendida'] }}</td>
                            <td class="col-qty">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="itens.{{ $index }}.qtd"
                                    class="erp-devvenda-input erp-devvenda-input--cell"
                                >
                            </td>
                            <td class="col-price">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="itens.{{ $index }}.preco"
                                    class="erp-devvenda-input erp-devvenda-input--cell"
                                >
                            </td>
                            <td class="col-total">{{ $item['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="erp-devvenda-itens__empty">
                                Selecione uma venda para carregar os itens.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="erp-devvenda-total">
            <span>TOTAL</span>
            <strong>R$ {{ $this->totalDisplay }}</strong>
        </div>
    </section>
</div>
