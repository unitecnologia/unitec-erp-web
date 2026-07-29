@if ($this->productPrecificacaoOpen)
    @php
        $p = $this->precificacao;
        $niveis = [
            'varejo' => ['label' => 'Preço Varejo', 'class' => 'varejo'],
            'atacado' => ['label' => 'Preço Atacado', 'class' => 'atacado'],
            'especial' => ['label' => 'Preço Especial', 'class' => 'especial'],
        ];
    @endphp
    <div
        class="erp-lookup-modal erp-prod-precificacao-modal"
        wire:keydown.escape.window="closeProductPrecificacao"
        wire:keydown.f5.window.prevent="aplicarProductPrecificacao"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeProductPrecificacao"></div>

        <div
            class="erp-lookup-modal__window erp-prod-precificacao-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-prod-precificacao-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-prod-precificacao-title">Precificação do Produto</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeProductPrecificacao" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-prod-precificacao-modal__body">
                <div class="erp-prod-precificacao__produto">
                    <div class="erp-prod-precificacao__field">
                        <label>Cód. Próprio</label>
                        <input type="text" value="{{ $p['codigo'] ?? '' }}" class="erp-pcad-form__input" readonly tabindex="-1">
                    </div>
                    <div class="erp-prod-precificacao__field">
                        <label>Cód. Barra</label>
                        <input type="text" value="{{ $p['codigo_barras'] ?? '' }}" class="erp-pcad-form__input" readonly tabindex="-1">
                    </div>
                    <div class="erp-prod-precificacao__field">
                        <label>Referência</label>
                        <input type="text" value="{{ $p['referencia'] ?? '' }}" class="erp-pcad-form__input" readonly tabindex="-1">
                    </div>
                    <div class="erp-prod-precificacao__field erp-prod-precificacao__field--grow">
                        <label>Descrição</label>
                        <input type="text" value="{{ $p['descricao'] ?? '' }}" class="erp-pcad-form__input" readonly tabindex="-1">
                    </div>
                    <div class="erp-prod-precificacao__field erp-prod-precificacao__field--empresa">
                        <label>Empresa</label>
                        <input type="text" value="{{ $p['empresa'] ?? '' }}" class="erp-pcad-form__input" readonly tabindex="-1">
                    </div>
                </div>

                <div class="erp-prod-precificacao__layout">
                    <fieldset class="erp-prod-precificacao__box erp-prod-precificacao__box--custo">
                        <legend>Formação de preço e custo</legend>

                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--compra">
                            <label for="precif-compra">Pr. Compra / Fornec.</label>
                            <div class="erp-prod-precificacao__cell erp-prod-precificacao__span-valores">
                                <input
                                    id="precif-compra"
                                    type="text"
                                    wire:model="precificacao.preco_compra"
                                    data-mask="money-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                            <label for="precif-pct-custos">Custos</label>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-pct-custos"
                                    type="text"
                                    wire:model="precificacao.pct_custos"
                                    data-mask="percent-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Percentual sobre a compra"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-custos-rs"
                                    type="text"
                                    wire:model="precificacao.custos_rs"
                                    data-mask="money-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Valor em R$"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                            <label for="precif-frete">Frete</label>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-frete"
                                    type="text"
                                    wire:model="precificacao.frete_pct"
                                    data-mask="percent-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Percentual sobre a compra"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-frete-rs"
                                    type="text"
                                    wire:model="precificacao.frete_rs"
                                    data-mask="money-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Valor em R$"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                            <label for="precif-outras-pct">Outras Despesas</label>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-outras-pct"
                                    type="text"
                                    wire:model="precificacao.outras_pct"
                                    data-mask="percent-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Percentual sobre a compra"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-outras"
                                    type="text"
                                    wire:model="precificacao.outras_desp"
                                    data-mask="money-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Valor em R$"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual erp-prod-precificacao__row--destaque">
                            <label for="precif-custo">Preço de Custo</label>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-custo-pct"
                                    type="text"
                                    wire:model="precificacao.custo_pct_total"
                                    class="erp-pcad-form__input erp-pcad-form__input--num erp-prod-precificacao__input--custo"
                                    readonly
                                    tabindex="-1"
                                    title="Soma de Custos + Frete + Outras Despesas"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-custo"
                                    type="text"
                                    wire:model="precificacao.preco_custo"
                                    class="erp-pcad-form__input erp-pcad-form__input--num erp-prod-precificacao__input--custo"
                                    readonly
                                    tabindex="-1"
                                    title="Resultado: Compra + Custos + Frete + Outras"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                    </fieldset>

                    <div class="erp-prod-precificacao__niveis">
                        <div class="erp-prod-precificacao__niveis-head">
                            <button
                                type="button"
                                class="erp-pcad-actions__btn erp-prod-precificacao__btn-padrao"
                                wire:click="aplicarPercentuaisPadraoPrecificacao"
                                title="Copia comissão, desconto e margem do varejo para atacado e especial"
                            >
                                <span class="erp-pcad-actions__label">Aplicar % do varejo</span>
                            </button>
                        </div>

                        <div class="erp-prod-precificacao__niveis-grid">
                            @foreach ($niveis as $key => $meta)
                                <fieldset class="erp-prod-precificacao__box erp-prod-precificacao__box--{{ $meta['class'] }}">
                                    <legend>{{ $meta['label'] }}</legend>

                                    <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                                        <label for="precif-{{ $key }}-comissao">Comissão</label>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-comissao"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.comissao"
                                                data-mask="percent-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Percentual de comissão"
                                            >
                                            <span class="erp-prod-precificacao__unit">%</span>
                                        </div>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-comissao-rs"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.comissao_rs"
                                                data-mask="money-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Valor em R$ da comissão"
                                            >
                                            <span class="erp-prod-precificacao__unit">R$</span>
                                        </div>
                                    </div>
                                    <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                                        <label for="precif-{{ $key }}-desconto">Desconto</label>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-desconto"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.desconto"
                                                data-mask="percent-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Percentual de desconto"
                                            >
                                            <span class="erp-prod-precificacao__unit">%</span>
                                        </div>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-desconto-rs"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.desconto_rs"
                                                data-mask="money-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Valor em R$ do desconto"
                                            >
                                            <span class="erp-prod-precificacao__unit">R$</span>
                                        </div>
                                    </div>
                                    <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                                        <label for="precif-{{ $key }}-margem">Margem</label>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-margem"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.margem"
                                                data-mask="percent-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Percentual de margem"
                                            >
                                            <span class="erp-prod-precificacao__unit">%</span>
                                        </div>
                                        <div class="erp-prod-precificacao__cell">
                                            <input
                                                id="precif-{{ $key }}-sugerido"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.sugerido"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                readonly
                                                tabindex="-1"
                                                title="Margem em R$ (custo + % margem)"
                                            >
                                            <span class="erp-prod-precificacao__unit">R$</span>
                                        </div>
                                    </div>
                                    <div class="erp-prod-precificacao__row erp-prod-precificacao__row--compra erp-prod-precificacao__row--praticado">
                                        <label for="precif-{{ $key }}-praticado">Praticado</label>
                                        <div class="erp-prod-precificacao__cell erp-prod-precificacao__span-valores">
                                            <input
                                                id="precif-{{ $key }}-praticado"
                                                type="text"
                                                wire:model="precificacao.niveis.{{ $key }}.praticado"
                                                data-mask="money-br"
                                                class="erp-pcad-form__input erp-pcad-form__input--num"
                                                title="Preço praticado (R$)"
                                            >
                                            <span class="erp-prod-precificacao__unit">R$</span>
                                        </div>
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-prod-precificacao__actions">
                <button
                    type="button"
                    wire:click="aplicarProductPrecificacao"
                    class="erp-pcad-actions__btn erp-prod-precificacao__btn erp-prod-precificacao__btn--aplicar"
                    data-erp-key="F5"
                    title="Aplicar preços (F5)"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label">Aplicar preços</span>
                </button>
            </div>
        </div>
    </div>
@endif
