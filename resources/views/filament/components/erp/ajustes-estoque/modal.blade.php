@if ($this->showAjusteForm)
    <div
        class="erp-fpgto-modal erp-ajuste-modal"
        x-data
        x-on:keydown.escape.window="$wire.handleAjusteEscape()"
        x-on:keydown.window="if ($event.key === 'F5') { $event.preventDefault(); $wire.saveAjusteForm(); }"
        x-on:erp-ajuste-focus-qtd.window="
            $nextTick(() => {
                const el = document.getElementById('erp-ajuste-qtd');
                if (!el || el.disabled) return;
                el.focus();
                el.select?.();
            })
        "
        x-on:erp-ajuste-focus-codigo-interno.window="
            $nextTick(() => {
                const el = document.getElementById('erp-ajuste-codigo-interno');
                if (!el || el.disabled) return;
                el.removeAttribute('readonly');
                el.focus();
                el.select?.();
            })
        "
        x-init="
            @if (! $this->ajusteFormId)
                $nextTick(() => {
                    const el = document.getElementById('erp-ajuste-codigo-interno');
                    if (!el || el.disabled) return;
                    el.removeAttribute('readonly');
                    el.focus();
                    el.select?.();
                })
            @endif
        "
    >
        <div class="erp-fpgto-modal__backdrop erp-ajuste-modal__backdrop" wire:click="closeAjusteForm"></div>

        <div class="erp-fpgto-modal__dialog erp-ajuste-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-ajuste-modal-title">
            <header class="erp-ajuste-modal__header">
                <div>
                    <p class="erp-ajuste-modal__eyebrow">Estoque</p>
                    <h2 id="erp-ajuste-modal-title" class="erp-ajuste-modal__title">
                        {{ $this->ajusteFormId ? 'Alterar ajuste' : 'Novo ajuste' }}
                    </h2>
                </div>
                <button type="button" class="erp-ajuste-modal__close" wire:click="closeAjusteForm" aria-label="Fechar">✕</button>
            </header>

            <div class="erp-fpgto-modal__body erp-ajuste-modal__body">
                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Identificação</span>
                    </div>
                    <div class="erp-ajuste-modal__meta">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. ajuste</span>
                            <input type="text" readonly tabindex="-1" value="{{ $this->ajusteForm['codigo_display'] ?? '' }}" class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--sm">
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Data</span>
                            <input type="date" wire:model="ajusteForm.data" class="erp-ajuste-modal__input erp-ajuste-modal__input--date">
                        </label>
                    </div>
                </section>

                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Produto</span>
                        <small>Enter nos códigos para localizar</small>
                    </div>

                    <div class="erp-ajuste-modal__row3">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. int.</span>
                            <input
                                id="erp-ajuste-codigo-interno"
                                type="text"
                                wire:model="ajusteForm.codigo_interno"
                                wire:keydown.enter="resolveProdutoCodigoInterno"
                                wire:blur="resolveProdutoCodigoInterno"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="Código"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Cód. barras</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.codigo_barras"
                                wire:keydown.enter="resolveProdutoCodigoBarras"
                                wire:blur="resolveProdutoCodigoBarras"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="EAN / barras"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Referência</span>
                            <input
                                type="text"
                                wire:model="ajusteForm.referencia"
                                wire:keydown.enter="resolveProdutoReferencia"
                                wire:blur="resolveProdutoReferencia"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="Referência"
                                data-erp-uppercase
                                autocomplete="off"
                            >
                        </label>
                    </div>

                    <div
                        class="erp-ajuste-modal__produto-busca"
                        x-data="{
                            ativo: 0,
                            mover(d) {
                                const lista = this.$refs.lista;
                                if (! lista) return;
                                const itens = lista.children;
                                const total = itens.length;
                                if (total === 0) return;
                                const atual = itens[this.ativo];
                                if (atual) {
                                    atual.classList.remove('is-selected');
                                    atual.setAttribute('aria-selected', 'false');
                                }
                                this.ativo = Math.max(0, Math.min(total - 1, this.ativo + d));
                                const prox = itens[this.ativo];
                                if (prox) {
                                    prox.classList.add('is-selected');
                                    prox.setAttribute('aria-selected', 'true');
                                    prox.scrollIntoView({ block: 'nearest' });
                                }
                            },
                            confirmar() {
                                const lista = this.$refs.lista;
                                const el = lista ? lista.children[this.ativo] : null;
                                const id = el ? Number(el.dataset.id || 0) : 0;
                                if (id > 0) {
                                    $wire.selecionarProdutoSugestao(id);
                                    return;
                                }
                                $wire.confirmarProdutoSugestao();
                            },
                            reset() {
                                this.ativo = 0;
                            }
                        }"
                        x-on:erp-ajuste-sugestoes-ready.window="reset()"
                    >
                        <label class="erp-ajuste-modal__field erp-ajuste-modal__field--full">
                            <span class="erp-ajuste-modal__label">Descrição</span>
                            <input
                                id="erp-ajuste-descricao-busca"
                                type="text"
                                wire:model.live.debounce.450ms="ajusteForm.descricao_busca"
                                wire:keydown.escape.prevent="fecharSugestoesProduto"
                                x-on:keydown.enter.prevent="confirmar()"
                                x-on:keydown.arrow-down.prevent="mover(1)"
                                x-on:keydown.arrow-up.prevent="mover(-1)"
                                @disabled($this->ajusteFormId)
                                class="erp-ajuste-modal__input"
                                placeholder="Digite nome, código, barras ou referência (mín. 2 letras)"
                                autocomplete="off"
                                data-erp-uppercase
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ count($this->produtoSugestoes) > 0 ? 'true' : 'false' }}"
                                aria-controls="erp-ajuste-produto-sugestoes"
                            >
                        </label>

                        @if (! $this->ajusteFormId && count($this->produtoSugestoes) > 0)
                            <div
                                id="erp-ajuste-produto-sugestoes"
                                class="erp-ajuste-modal__sugestoes"
                                role="listbox"
                                aria-label="Produtos encontrados"
                                x-ref="lista"
                            >
                                @foreach ($this->produtoSugestoes as $index => $sugestao)
                                    <button
                                        type="button"
                                        id="erp-ajuste-produto-sug-{{ $index }}"
                                        wire:key="erp-ajuste-prod-sug-{{ $sugestao['id'] }}"
                                        data-id="{{ $sugestao['id'] }}"
                                        wire:click="selecionarProdutoSugestao({{ $sugestao['id'] }})"
                                        x-on:mouseenter="
                                            const lista = $refs.lista;
                                            if (! lista) return;
                                            const prev = lista.children[ativo];
                                            if (prev) {
                                                prev.classList.remove('is-selected');
                                                prev.setAttribute('aria-selected', 'false');
                                            }
                                            ativo = {{ $index }};
                                            const cur = lista.children[ativo];
                                            if (cur) {
                                                cur.classList.add('is-selected');
                                                cur.setAttribute('aria-selected', 'true');
                                            }
                                        "
                                        @class([
                                            'erp-ajuste-modal__sugestao',
                                            'is-selected' => $index === 0,
                                        ])
                                        role="option"
                                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                        tabindex="-1"
                                    >
                                        <span class="erp-ajuste-modal__sugestao-cod">{{ $sugestao['codigo'] }}</span>
                                        <span class="erp-ajuste-modal__sugestao-barras" title="{{ $sugestao['codigo_barras'] ?: '—' }}">{{ $sugestao['codigo_barras'] ?: '—' }}</span>
                                        <span class="erp-ajuste-modal__sugestao-desc">{{ $sugestao['descricao'] }}</span>
                                        <span class="erp-ajuste-modal__sugestao-est">Est. {{ $sugestao['estoque'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (filled($this->ajusteForm['product_id'] ?? null))
                        <p class="erp-ajuste-modal__produto-ok">
                            Produto selecionado: <strong>{{ $this->ajusteForm['descricao_busca'] ?? '' }}</strong>
                        </p>
                    @endif
                </section>

                <section class="erp-ajuste-modal__section erp-ajuste-modal__section--qty">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Quantidade</span>
                        <small>
                            @if (($this->ajusteForm['modo'] ?? 'somar') === 'substituir')
                                valor informado vira o estoque final
                            @else
                                + entrada · − saída
                            @endif
                        </small>
                    </div>

                    <div class="erp-ajuste-modal__modo" role="radiogroup" aria-label="Modo do ajuste">
                        <label class="erp-ajuste-modal__modo-opt">
                            <input
                                type="radio"
                                wire:model.live="ajusteForm.modo"
                                value="somar"
                            >
                            <span>Somar</span>
                        </label>
                        <label class="erp-ajuste-modal__modo-opt">
                            <input
                                type="radio"
                                wire:model.live="ajusteForm.modo"
                                value="substituir"
                            >
                            <span>Substituir</span>
                        </label>
                    </div>

                    <div class="erp-ajuste-modal__row2">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Estoque atual</span>
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                value="{{ $this->ajusteForm['estoque_atual'] ?? '' }}"
                                class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--num"
                                aria-readonly="true"
                                onfocus="this.blur()"
                            >
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">
                                {{ ($this->ajusteForm['modo'] ?? 'somar') === 'substituir' ? 'Novo estoque' : 'Qtd. ajuste' }}
                            </span>
                            <input
                                id="erp-ajuste-qtd"
                                type="text"
                                wire:model="ajusteForm.quantidade"
                                inputmode="decimal"
                                class="erp-ajuste-modal__input erp-ajuste-modal__input--num erp-ajuste-modal__input--emphasis"
                                placeholder="0"
                            >
                        </label>
                    </div>
                    <p class="erp-ajuste-modal__hint">
                        @if (($this->ajusteForm['modo'] ?? 'somar') === 'substituir')
                            Substituir: o valor informado vira o estoque final (ex.: atual −7 e informar 10 → entrada de 17 para ficar 10).
                        @else
                            Somar: quantidade positiva entra estoque; negativa sai.
                        @endif
                        Pressione <kbd>Enter</kbd> nos campos de código para localizar.
                    </p>
                </section>
            </div>

            <footer class="erp-ajuste-modal__footer">
                <button type="button" wire:click="closeAjusteForm" class="erp-ajuste-modal__btn erp-ajuste-modal__btn--ghost">
                    Cancelar
                </button>
                <button type="button" wire:click="saveAjusteForm" class="erp-ajuste-modal__btn erp-ajuste-modal__btn--primary">
                    <kbd>F5</kbd>
                    <span>Gravar</span>
                </button>
            </footer>
        </div>
    </div>
@endif
