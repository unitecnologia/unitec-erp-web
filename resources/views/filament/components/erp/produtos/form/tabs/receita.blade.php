@php
    $editingReceita = $this->selectedReceitaIndex !== null;
    $editingRow = $editingReceita && isset($this->receitaRows[$this->selectedReceitaIndex])
        ? $this->receitaRows[$this->selectedReceitaIndex]
        : null;
    $hasSuggestions = $this->receitaSuggestionsOpen && $this->receitaProductSuggestions !== [];
    $custoTotal = $this->receitaCustoTotal();
    $custoUnit = $this->receitaCustoUnitarioProduzido();
@endphp

<div class="erp-produtos-child-grid erp-produtos-composicao erp-produtos-receita">
    <p class="erp-produtos-receita__hint">
        Receita global do produto (compartilhada entre empresas). A baixa de estoque na fabricação será por empresa/depósito.
        Custo estimado usa <strong>preço de custo</strong> (ou compra) — nunca o preço de venda. Sem conversão automática de unidades.
    </p>

    <div class="erp-produtos-composicao__form">
        <div class="erp-produtos-receita__meta">
            <div class="erp-produtos-composicao__field erp-produtos-receita__field--rendimento">
                <label for="pprod-receita-rendimento">Rendimento (qtd produzida)</label>
                <input
                    id="pprod-receita-rendimento"
                    type="text"
                    wire:model.blur="receitaQtdProduzida"
                    data-mask="decimal3"
                    inputmode="decimal"
                    class="erp-pcad-form__input erp-produtos-child-grid__input-num"
                >
            </div>
            <label class="erp-produtos-receita__ativo" title="Receita inativa não será usada na fabricação (etapa futura).">
                <input type="checkbox" wire:model="receitaAtiva">
                Receita ativa
            </label>
            <div class="erp-produtos-receita__custos" title="Estimativa — não altera o cadastro do produto">
                <span>Custo total <strong>R$ {{ number_format($custoTotal, 2, ',', '.') }}</strong></span>
                <span>Custo unit. produzido <strong>R$ {{ number_format($custoUnit, 2, ',', '.') }}</strong></span>
            </div>
        </div>

        <div class="erp-produtos-composicao__toolbar">
            <div class="erp-produtos-composicao__field erp-produtos-composicao__field--produto">
                <label for="pprod-receita-codigo">Ingrediente (código / nome)</label>
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
                                    $wire.selectReceitaSuggestion(id);
                                    return;
                                }
                            }
                            $wire.confirmReceitaSuggestionFromKeyboard();
                        },
                        reset() {
                            this.ativo = 0;
                        }
                    }"
                >
                    <input
                        id="pprod-receita-codigo"
                        type="text"
                        wire:model.live.debounce.200ms="receitaProductSearch"
                        wire:focus="openReceitaSuggestions"
                        wire:keydown.escape.prevent="closeReceitaSuggestions"
                        x-on:keydown.enter.prevent="selecionar()"
                        x-on:keydown.arrow-down.prevent="mover(1)"
                        x-on:keydown.arrow-up.prevent="mover(-1)"
                        class="erp-pcad-form__input"
                        placeholder="Buscar ingrediente ativo"
                        autocomplete="off"
                        data-erp-uppercase
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $hasSuggestions ? 'true' : 'false' }}"
                        aria-controls="pprod-receita-sugestoes"
                        data-erp-receita-field="search"
                    >

                    @if ($hasSuggestions)
                        <div class="erp-produtos-composicao__suggest-wrap" wire:key="pprod-receita-sug-wrap-{{ md5(json_encode($this->receitaProductSuggestions)) }}">
                            <ul
                                id="pprod-receita-sugestoes"
                                class="erp-produtos-composicao__suggest"
                                role="listbox"
                                aria-label="Ingredientes encontrados"
                                x-ref="lista"
                                x-init="reset()"
                            >
                                @foreach ($this->receitaProductSuggestions as $index => $sug)
                                    <li wire:key="pprod-receita-sug-{{ $sug['id'] }}" role="presentation">
                                        <button
                                            type="button"
                                            role="option"
                                            data-id="{{ $sug['id'] }}"
                                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                            wire:click="selectReceitaSuggestion({{ $sug['id'] }})"
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
                                            <span class="erp-produtos-composicao__suggest-preco">Custo R$ {{ $sug['custo'] ?? '0,00' }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif ($this->receitaSuggestionsOpen && filled($this->receitaProductSearch) && ! $this->receitaPendingProductId)
                        <div class="erp-produtos-composicao__suggest erp-produtos-composicao__suggest--empty">
                            Nenhum ingrediente encontrado.
                        </div>
                    @endif
                </div>
            </div>

            <div class="erp-produtos-composicao__field erp-produtos-composicao__field--qtd">
                <label for="pprod-receita-qtd">Qtd</label>
                <input
                    id="pprod-receita-qtd"
                    type="text"
                    wire:model="receitaQuantidade"
                    wire:keydown.enter.prevent="confirmReceitaFromQtd($event.target.value)"
                    data-mask="decimal3"
                    inputmode="decimal"
                    class="erp-pcad-form__input erp-produtos-child-grid__input-num"
                    data-erp-receita-field="qtd"
                >
            </div>

            <div class="erp-produtos-composicao__actions">
                @if ($editingReceita)
                    <button type="button" wire:click="updateReceitaItem" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--primary">Atualizar</button>
                    <button type="button" wire:click="deleteReceitaItem" class="erp-produtos-child-grid__btn">Excluir</button>
                    <button type="button" wire:click="clearReceitaSelection" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--ghost">Limpar</button>
                @else
                    <button type="button" wire:click="addReceitaItem" class="erp-produtos-child-grid__btn erp-produtos-composicao__btn--primary">Incluir</button>
                @endif
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
                    <th class="erp-produtos-composicao__col-produto">Ingrediente</th>
                    <th class="erp-produtos-receita__col-un">UN</th>
                    <th class="erp-produtos-composicao__col-num">Qtd</th>
                    <th class="erp-produtos-composicao__col-num">Custo</th>
                    <th class="erp-produtos-composicao__col-num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->receitaRows as $index => $row)
                    <tr
                        wire:click="selectReceitaRow({{ $index }})"
                        @class(['erp-produtos-child-grid__row', 'erp-produtos-child-grid__row--selected' => $this->selectedReceitaIndex === $index])
                    >
                        <td class="erp-produtos-composicao__col-codigo">{{ $row['codigo'] ?? '—' }}</td>
                        <td class="erp-produtos-composicao__col-produto">{{ $row['descricao'] ?? '—' }}</td>
                        <td class="erp-produtos-receita__col-un">{{ $row['unidade'] ?? '—' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['quantidade'] ?? '0,000' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['custo_unitario'] ?? '0,00' }}</td>
                        <td class="erp-produtos-child-grid__num">{{ $row['custo_total'] ?? '0,00' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="erp-produtos-child-grid__empty">
                            Nenhum ingrediente. Busque e clique em Incluir. Grave o produto (F5) para persistir a receita.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
