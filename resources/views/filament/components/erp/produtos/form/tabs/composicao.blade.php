@php
    $editingComposition = $this->selectedCompositionIndex !== null;
    $editingRow = $editingComposition && isset($this->compositionRows[$this->selectedCompositionIndex])
        ? $this->compositionRows[$this->selectedCompositionIndex]
        : null;
    $hasSuggestions = $this->compositionSuggestionsOpen && $this->compositionProductSuggestions !== [];
@endphp

<div class="erp-produtos-child-grid erp-produtos-composicao">
    <div class="erp-produtos-composicao__form">
        <div class="erp-produtos-composicao__toolbar">
            <div class="erp-produtos-composicao__field erp-produtos-composicao__field--produto">
                <label for="pprod-comp-codigo">Código / nome</label>
                <div
                    class="erp-produtos-composicao__lookup"
                    x-data="{
                        ativo: 0,
                        mover(d) {
                            const lista = this.$refs.lista;
                            if (! lista) return;
                            const itens = lista.querySelectorAll('[data-id]');
                            const total = itens.length;
                            if (total === 0) return;
                            const atual = itens[this.ativo];
                            if (atual) {
                                atual.classList.remove('is-selected');
                                atual.setAttribute('aria-selected', 'false');
                            }
                            this.ativo = (this.ativo + d + total) % total;
                            const prox = itens[this.ativo];
                            if (prox) {
                                prox.classList.add('is-selected');
                                prox.setAttribute('aria-selected', 'true');
                                prox.scrollIntoView({ block: 'nearest' });
                            }
                        },
                        selecionar() {
                            const lista = this.$refs.lista;
                            if (lista) {
                                const itens = lista.querySelectorAll('[data-id]');
                                const el = itens[this.ativo];
                                const id = el ? Number(el.getAttribute('data-id') || 0) : 0;
                                if (id > 0) {
                                    $wire.selectCompositionSuggestion(id);
                                    return;
                                }
                            }
                            $wire.confirmCompositionSuggestionFromKeyboard();
                        },
                        reset() {
                            this.ativo = 0;
                        }
                    }"
                >
                    <input
                        id="pprod-comp-codigo"
                        type="text"
                        wire:model.live.debounce.200ms="compositionProductSearch"
                        wire:focus="openCompositionSuggestions"
                        wire:keydown.escape.prevent="closeCompositionSuggestions"
                        x-on:keydown.enter.prevent="selecionar()"
                        x-on:keydown.arrow-down.prevent="mover(1)"
                        x-on:keydown.arrow-up.prevent="mover(-1)"
                        class="erp-pcad-form__input"
                        placeholder="Buscar por código ou descrição"
                        autocomplete="off"
                        data-erp-uppercase
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $hasSuggestions ? 'true' : 'false' }}"
                        aria-controls="pprod-comp-sugestoes"
                    >

                    @if ($hasSuggestions)
                        <div class="erp-produtos-composicao__suggest-wrap" wire:key="pprod-comp-sug-wrap-{{ md5(json_encode($this->compositionProductSuggestions)) }}">
                            <ul
                                id="pprod-comp-sugestoes"
                                class="erp-produtos-composicao__suggest"
                                role="listbox"
                                aria-label="Produtos encontrados"
                                x-ref="lista"
                                x-init="reset()"
                            >
                                @foreach ($this->compositionProductSuggestions as $index => $sug)
                                    <li wire:key="pprod-comp-sug-{{ $sug['id'] }}" role="presentation">
                                        <button
                                            type="button"
                                            role="option"
                                            data-id="{{ $sug['id'] }}"
                                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                            wire:click="selectCompositionSuggestion({{ $sug['id'] }})"
                                            x-on:mouseenter="
                                                const lista = $refs.lista;
                                                if (! lista) return;
                                                const itens = lista.querySelectorAll('[data-id]');
                                                const prev = itens[ativo];
                                                if (prev) {
                                                    prev.classList.remove('is-selected');
                                                    prev.setAttribute('aria-selected', 'false');
                                                }
                                                ativo = {{ $index }};
                                                const cur = itens[ativo];
                                                if (cur) {
                                                    cur.classList.add('is-selected');
                                                    cur.setAttribute('aria-selected', 'true');
                                                }
                                            "
                                            @class(['is-selected' => $index === 0])
                                        >
                                            <span class="erp-produtos-composicao__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                            <span class="erp-produtos-composicao__suggest-nome">{{ $sug['descricao'] ?? '—' }}</span>
                                            <span class="erp-produtos-composicao__suggest-preco">R$ {{ $sug['preco'] ?? '0,00' }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($this->compositionSuggestionsOpen && filled($this->compositionProductSearch) && ! $this->compositionPendingProductId)
                        <div class="erp-produtos-composicao__suggest erp-produtos-composicao__suggest--empty">
                            Nenhum produto encontrado.
                        </div>
                    @endif
                </div>
            </div>

            <div class="erp-produtos-composicao__field erp-produtos-composicao__field--qtd">
                <label for="pprod-comp-qtd">Qtd</label>
                <input
                    id="pprod-comp-qtd"
                    type="text"
                    wire:model="compositionQuantidade"
                    data-mask="decimal3"
                    inputmode="decimal"
                    class="erp-pcad-form__input erp-produtos-child-grid__input-num"
                    data-erp-composicao-field="qtd"
                >
            </div>

            <div class="erp-produtos-composicao__field erp-produtos-composicao__field--preco">
                <label for="pprod-comp-preco">Preço</label>
                <input
                    id="pprod-comp-preco"
                    type="text"
                    wire:model="compositionPreco"
                    data-mask="money-br"
                    inputmode="decimal"
                    class="erp-pcad-form__input erp-produtos-child-grid__input-num"
                    data-erp-composicao-field="preco"
                >
            </div>

            <div class="erp-produtos-composicao__actions">
                @if ($editingComposition)
                    <button type="button" wire:click="updateCompositionItem" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--primary">Atualizar</button>
                    <button type="button" wire:click="deleteCompositionItem" class="erp-produtos-child-grid__btn">Excluir</button>
                    <button type="button" wire:click="clearCompositionSelection" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--ghost">Limpar</button>
                @else
                    <button type="button" wire:click="addCompositionItem" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--primary">Incluir</button>
                @endif
            </div>

            <div class="erp-produtos-composicao__total" title="Soma dos totais dos componentes">
                <span class="erp-produtos-composicao__total-label">Total</span>
                <strong class="erp-produtos-composicao__total-value">R$ {{ number_format($this->compositionRowsTotal(), 2, ',', '.') }}</strong>
            </div>
        </div>

        @if ($editingRow)
            <p class="erp-produtos-composicao__editing">
                Editando <strong>{{ $editingRow['codigo'] ?? '' }}</strong>
                — {{ $editingRow['descricao'] ?? '' }}
            </p>
        @endif
    </div>

    <div class="erp-produtos-child-grid__wrap erp-produtos-composicao__grid">
        <table class="erp-produtos-child-grid__table erp-produtos-composicao__table">
            <thead>
                <tr>
                    <th class="erp-produtos-composicao__col-codigo">Código</th>
                    <th class="erp-produtos-composicao__col-produto">Produto</th>
                    <th class="erp-produtos-composicao__col-adicional">Adicional</th>
                    <th class="erp-produtos-composicao__col-num">Qtd</th>
                    <th class="erp-produtos-composicao__col-num">Preço</th>
                    <th class="erp-produtos-composicao__col-num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->compositionRows as $index => $row)
                    <tr
                        wire:click="selectCompositionRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedCompositionIndex === $index])
                    >
                        <td class="erp-produtos-composicao__col-codigo">{{ $row['codigo'] ?? '—' }}</td>
                        <td class="erp-produtos-composicao__col-produto">{{ $row['descricao'] ?? '—' }}</td>
                        <td class="erp-produtos-composicao__col-adicional erp-produtos-child-grid__check">
                            <input
                                type="checkbox"
                                wire:model="compositionRows.{{ $index }}.is_adicional"
                                wire:click.stop
                                title="Marcado = adicional"
                                aria-label="Adicional"
                            >
                        </td>
                        <td class="erp-produtos-child-grid__num">{{ $row['quantidade'] ?? '0,000' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['preco'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['total'] ?? '0,00' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="erp-produtos-child-grid__empty">
                            Nenhum componente. Busque por código ou nome e clique em Incluir.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
