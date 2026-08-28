@if ($this->showForm)
    @php
        $empresas = $this->empresaOptions();
    @endphp
    <div
        class="erp-promo-modal"
        x-data
        x-on:keydown.escape.window="$wire.closeForm()"
        x-on:keydown.window="
            if ($event.key === 'F2') { $event.preventDefault(); $wire.savePromocao(); }
        "
    >
        <div class="erp-promo-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-promo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-promo-title">
            <div class="erp-promo-modal__titlebar">
                <span id="erp-promo-title">{{ $this->formId ? 'Alterar promoção' : 'Nova promoção' }}</span>
                <button type="button" class="erp-promo-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-promo-modal__body">
                <section class="erp-promo-modal__header">
                    <label class="erp-promo-field erp-promo-field--wide">
                        <span>Descrição</span>
                        <input type="text" wire:model="form.descricao" maxlength="120" class="erp-promo-field__input" data-erp-uppercase autofocus>
                    </label>
                    @error('form.descricao') <p class="erp-promo-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-promo-field">
                        <span>Início</span>
                        <input type="date" wire:model="form.data_inicio" class="erp-promo-field__input" data-erp-native-date>
                    </label>
                    @error('form.data_inicio') <p class="erp-promo-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-promo-field">
                        <span>Fim</span>
                        <input type="date" wire:model="form.data_fim" class="erp-promo-field__input" data-erp-native-date>
                    </label>
                    @error('form.data_fim') <p class="erp-promo-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-promo-field">
                        <span>Empresa</span>
                        <select wire:model="form.empresa_id" class="erp-promo-field__input">
                            <option value="">— Selecione —</option>
                            @foreach ($empresas as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('form.empresa_id') <p class="erp-promo-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-promo-field erp-promo-field--check">
                        <span>Ativa</span>
                        <input type="checkbox" wire:model="form.ativa">
                    </label>
                </section>

                <section class="erp-promo-modal__produtos">
                    <div class="erp-promo-modal__produtos-head">
                        <h3>Produtos</h3>
                        <div class="erp-promo-add">
                            <input
                                type="text"
                                class="erp-promo-field__input erp-promo-add__input"
                                placeholder="Código ou nome…"
                                wire:model.live.debounce.200ms="itemProdutoSearch"
                                wire:focus="abrirProdutoLookup"
                                wire:keydown.enter.prevent="confirmarInclusaoProduto($event.target.value)"
                                wire:keydown.arrow-down.prevent="moverProdutoSelecionado(1)"
                                wire:keydown.arrow-up.prevent="moverProdutoSelecionado(-1)"
                                autocomplete="off"
                                data-erp-uppercase
                            >
                            <button type="button" class="erp-promo-add__btn" wire:click="confirmarInclusaoProduto">+ Adicionar</button>
                        </div>
                    </div>

                    @if ($this->produtoLookupOpen && $this->produtoResults !== [])
                        <div class="erp-promo-lookup">
                            @foreach ($this->produtoResults as $i => $sug)
                                <button
                                    type="button"
                                    class="erp-promo-lookup__row {{ $this->produtoSelecionadoIndex === $i ? 'is-selected' : '' }}"
                                    wire:click="selecionarProdutoInclusao({{ (int) $sug['id'] }})"
                                >
                                    <span class="erp-promo-lookup__code">{{ $sug['codigo'] }}</span>
                                    <span class="erp-promo-lookup__name">{{ $sug['descricao'] }}</span>
                                    <span class="erp-promo-lookup__price">R$ {{ $sug['preco'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="erp-promo-grid-wrap">
                        <table class="erp-promo-grid">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="is-num">Normal</th>
                                    <th class="is-num">Promoção</th>
                                    <th class="is-center">Mostrar PDV</th>
                                    <th class="is-center">Mostrar TV</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->itens as $index => $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item['codigo'] }}</strong>
                                            <span>{{ $item['descricao'] }}</span>
                                        </td>
                                        <td class="is-num">{{ $item['preco_normal'] }}</td>
                                        <td class="is-num">
                                            <input
                                                type="text"
                                                class="erp-promo-grid__input"
                                                value="{{ $item['preco_promocao'] }}"
                                                data-mask="money-br"
                                                data-erp-allow-browser-hints="1"
                                                wire:keydown.enter.prevent="atualizarPrecoPromocao({{ $index }}, $event.target.value)"
                                                wire:blur="atualizarPrecoPromocao({{ $index }}, $event.target.value)"
                                            >
                                        </td>
                                        <td class="is-center">
                                            <input
                                                type="checkbox"
                                                @checked($item['mostrar_pdv'] ?? false)
                                                wire:click="toggleMostrarPdv({{ $index }})"
                                            >
                                        </td>
                                        <td class="is-center">
                                            <div class="erp-promo-grid__tv-soon">
                                                <input type="checkbox" disabled>
                                                <span class="erp-promo-grid__soon-badge">Em breve</span>
                                            </div>
                                        </td>
                                        <td class="is-center">
                                            <button type="button" class="erp-promo-grid__remove" wire:click="removerItemPromocao({{ $index }})" title="Remover">×</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="erp-promo-grid__empty">Nenhum produto. Busque e adicione acima.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="erp-promo-modal__footer">
                <button type="button" class="erp-promo-modal__btn" wire:click="closeForm">Cancelar</button>
                <button type="button" class="erp-promo-modal__btn erp-promo-modal__btn--primary" wire:click="savePromocao">
                    <kbd>F2</kbd> Salvar
                </button>
            </div>
        </div>
    </div>
@endif
