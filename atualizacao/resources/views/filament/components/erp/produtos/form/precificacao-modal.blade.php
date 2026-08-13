@if ($this->productPrecificacaoOpen)
    @php
        $p = $this->precificacao;
        $epoch = $this->precificacaoUiEpoch;
        $niveis = [
            'varejo' => ['label' => 'Preço Varejo', 'class' => 'varejo'],
            'atacado' => ['label' => 'Preço Atacado', 'class' => 'atacado'],
            'especial' => ['label' => 'Preço Especial', 'class' => 'especial'],
        ];

        // O Livewire não atualiza value= de input com foco. O JS repinta a partir daqui.
        $precifValores = [
            'precif-compra' => $p['preco_compra'] ?? '',
            'precif-pct-custos' => $p['pct_custos'] ?? '',
            'precif-custos-rs' => $p['custos_rs'] ?? '',
            'precif-frete' => $p['frete_pct'] ?? '',
            'precif-frete-rs' => $p['frete_rs'] ?? '',
            'precif-seguro' => $p['seguro_pct'] ?? '',
            'precif-seguro-rs' => $p['seguro_rs'] ?? '',
            'precif-outras-pct' => $p['outras_pct'] ?? '',
            'precif-outras' => $p['outras_desp'] ?? '',
            'precif-custo-pct' => $p['custo_pct_total'] ?? '',
            'precif-custo' => $p['preco_custo'] ?? '',
        ];

        foreach (array_keys($niveis) as $nivelKey) {
            $nivelDados = $p['niveis'][$nivelKey] ?? [];

            $precifValores['precif-'.$nivelKey.'-comissao'] = $nivelDados['comissao'] ?? '';
            $precifValores['precif-'.$nivelKey.'-comissao-rs'] = $nivelDados['comissao_rs'] ?? '';
            $precifValores['precif-'.$nivelKey.'-desconto'] = $nivelDados['desconto'] ?? '';
            $precifValores['precif-'.$nivelKey.'-desconto-rs'] = $nivelDados['desconto_rs'] ?? '';
            $precifValores['precif-'.$nivelKey.'-margem'] = $nivelDados['margem'] ?? '';
            $precifValores['precif-'.$nivelKey.'-sugerido'] = $nivelDados['sugerido'] ?? '';
            $precifValores['precif-'.$nivelKey.'-praticado'] = $nivelDados['praticado'] ?? '';
        }
    @endphp
    <div
        class="erp-lookup-modal erp-prod-precificacao-modal"
        wire:keydown.escape.window="closeProductPrecificacao"
        x-on:keydown.f5.window.prevent="
            const el = document.activeElement;
            const id = el && el.id ? String(el.id) : '';
            const val = el && 'value' in el ? String(el.value) : null;
            const aplicar = () => $wire.aplicarProductPrecificacao();
            if (id.indexOf('precif-') === 0 && val !== null) {
                $wire.precificacaoCommitField(id, val).then(aplicar);
            } else {
                aplicar();
            }
        "
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeProductPrecificacao"></div>

        <div
            class="erp-lookup-modal__window erp-prod-precificacao-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-prod-precificacao-title"
            x-data
            @keydown.enter="
                const el = $event.target;
                if (! (el instanceof HTMLInputElement) || el.disabled) {
                    return;
                }
                if (! el.hasAttribute('data-erp-precif-enter')) {
                    return;
                }

                $event.preventDefault();

                const api = window.ErpPrecifEnter;
                if (! api || ! api.prepareEnter) {
                    return;
                }

                const info = api.prepareEnter(el);
                if (! info) {
                    return;
                }

                // Um único request. O PHP recalcula todo o estado e só então
                // devolve o foco pelo evento erp-precif-focus.
                $wire.precificacaoEnter(info.fieldId, info.value, info.diag ?? null);
            "
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-prod-precificacao-title">Precificação do Produto</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeProductPrecificacao" title="Fechar">✕</button>
            </div>

            {{-- Chave estável: recriar o corpo a cada Enter matava o foco e gerava blur fantasma.
                 Os valores são repintados pelo erp-precif-enter-v5.js a partir do value= do servidor. --}}
            <div
                class="erp-lookup-modal__body erp-prod-precificacao-modal__body"
                wire:key="erp-precif-body"
                data-precif-epoch="{{ $epoch }}"
                data-precif-values="{{ json_encode($precifValores, JSON_UNESCAPED_UNICODE) }}"
            >
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['preco_compra'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['pct_custos'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['custos_rs'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['frete_pct'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['frete_rs'] ?? '' }}"
                                    data-mask="money-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Valor em R$"
                                >
                                <span class="erp-prod-precificacao__unit">R$</span>
                            </div>
                        </div>
                        <div class="erp-prod-precificacao__row erp-prod-precificacao__row--dual">
                            <label for="precif-seguro">Seguro</label>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-seguro"
                                    type="text"
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['seguro_pct'] ?? '' }}"
                                    data-mask="percent-br"
                                    class="erp-pcad-form__input erp-pcad-form__input--num"
                                    title="Percentual sobre a compra"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-seguro-rs"
                                    type="text"
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['seguro_rs'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['outras_pct'] ?? '' }}"
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
                                    data-erp-precif-enter
                                    autocomplete="one-time-code"
                                    value="{{ $p['outras_desp'] ?? '' }}"
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
                                    value="{{ $p['custo_pct_total'] ?? '' }}"
                                    class="erp-pcad-form__input erp-pcad-form__input--num erp-prod-precificacao__input--custo"
                                    readonly
                                    tabindex="-1"
                                    title="Soma de Custos + Frete + Seguro + Outras Despesas"
                                >
                                <span class="erp-prod-precificacao__unit">%</span>
                            </div>
                            <div class="erp-prod-precificacao__cell">
                                <input
                                    id="precif-custo"
                                    type="text"
                                    value="{{ $p['preco_custo'] ?? '' }}"
                                    class="erp-pcad-form__input erp-pcad-form__input--num erp-prod-precificacao__input--custo"
                                    readonly
                                    tabindex="-1"
                                    title="Resultado: Compra + Custos + Frete + Seguro + Outras"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['comissao'] ?? '' }}"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['comissao_rs'] ?? '' }}"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['desconto'] ?? '' }}"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['desconto_rs'] ?? '' }}"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['margem'] ?? '' }}"
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
                                                value="{{ $p['niveis'][$key]['sugerido'] ?? '' }}"
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
                                                data-erp-precif-enter
                                    autocomplete="one-time-code"
                                                value="{{ $p['niveis'][$key]['praticado'] ?? '' }}"
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
                    x-on:click.prevent="
                        const el = document.activeElement;
                        const id = el && el.id ? String(el.id) : '';
                        const val = el && 'value' in el ? String(el.value) : null;
                        const aplicar = () => $wire.aplicarProductPrecificacao();
                        if (id.indexOf('precif-') === 0 && val !== null) {
                            $wire.precificacaoCommitField(id, val).then(aplicar);
                        } else {
                            aplicar();
                        }
                    "
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
