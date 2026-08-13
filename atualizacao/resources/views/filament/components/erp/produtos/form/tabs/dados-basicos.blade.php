@php
    use App\Models\Product;
@endphp

<div
    class="erp-produtos-form"
    x-data
    @keydown.enter="
        const el = $event.target;
        if (
            (! (el instanceof HTMLInputElement) && ! (el instanceof HTMLSelectElement))
            || el.disabled
        ) {
            return;
        }
        if (! el.hasAttribute('data-erp-prod-enter')) {
            return;
        }

        $event.preventDefault();

        const fields = Array.from($el.querySelectorAll(
            'input[data-erp-prod-enter]:not([disabled]), select[data-erp-prod-enter]:not([disabled])'
        )).filter((field) => field.offsetParent !== null);

        const idx = fields.indexOf(el);
        const next = idx >= 0 ? (fields[idx + 1] ?? null) : null;

        el.blur();

        if (! next) {
            return;
        }

        const tryFocus = (attempt = 0) => {
            next.focus();
            if (document.activeElement === next || attempt >= 12) {
                if (next instanceof HTMLInputElement && ! next.readOnly) {
                    next.select();
                }
                return;
            }
            setTimeout(() => tryFocus(attempt + 1), 30 + (attempt * 20));
        };

        setTimeout(() => tryFocus(0), 0);
    "
>
    <section class="erp-produtos-form__section erp-produtos-form__section--id">
        <h3 class="erp-produtos-form__section-title">Identificação</h3>
        <div class="erp-produtos-form__grid erp-produtos-form__grid--r1">
            <div class="erp-produtos-form__cell erp-produtos-form__cell--codigo">
                <label for="pprod-codigo">Código</label>
                <input
                    id="pprod-codigo"
                    type="text"
                    value="{{ $this->data['codigo'] ?? '' }}"
                    disabled
                    tabindex="-1"
                    aria-readonly="true"
                    class="erp-pcad-form__input erp-produtos-form__input--codigo erp-produtos-form__input--info"
                >
                <input type="hidden" wire:model="data.codigo">
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--required erp-produtos-form__cell--descricao">
                <label for="pprod-descricao">Descrição</label>
                <input id="pprod-descricao" type="text" wire:model="data.descricao" data-erp-prod-enter class="erp-pcad-form__input">
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--barras">
                <label for="pprod-barras">Código de Barras</label>
                <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                    <input
                        id="pprod-barras"
                        type="text"
                        inputmode="numeric"
                        wire:model="data.codigo_barras"
                        wire:blur="normalizeProductBarcodeOnBlur"
                        class="erp-pcad-form__input"
                        name="erp_barcode_main"
                        autocomplete="one-time-code"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        data-bwignore="true"
                        data-erp-prod-enter
                        readonly
                        onfocus="this.removeAttribute('readonly')"
                    >
                    <button
                        type="button"
                        data-erp-search-barcode
                        wire:loading.attr="disabled"
                        wire:target="searchCodigoBarras"
                        class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                        title="Pesquisar código de barras"
                    >
                        <span class="erp-pcad-form__btn-icon" wire:loading.remove wire:target="searchCodigoBarras">🔍</span>
                        <span wire:loading wire:target="searchCodigoBarras">…</span>
                    </button>
                </div>
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--barras-caixa">
                <label for="pprod-barras-caixa">Cód. Barras (Caixa)</label>
                <input
                    id="pprod-barras-caixa"
                    type="text"
                    inputmode="numeric"
                    wire:model="data.codigo_barras_caixa"
                    wire:blur="normalizeProductBarcodeCaixaOnBlur"
                    class="erp-pcad-form__input"
                    name="erp_barcode_caixa"
                    autocomplete="one-time-code"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    data-form-type="other"
                    data-bwignore="true"
                    data-erp-prod-enter
                    readonly
                    onfocus="this.removeAttribute('readonly')"
                >
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--referencia">
                <label for="pprod-referencia">Referência</label>
                <input id="pprod-referencia" type="text" wire:model="data.referencia" data-erp-prod-enter class="erp-pcad-form__input">
            </div>
        </div>
    </section>

    <section class="erp-produtos-form__section erp-produtos-form__section--class">
        <h3 class="erp-produtos-form__section-title">Classificação</h3>
        <div class="erp-produtos-form__grid erp-produtos-form__grid--r2">
            <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-tipo">
                <label for="pprod-tipo">Tipo de Produto</label>
                @include('filament.components.erp.produtos.form.compact-select', [
                    'id' => 'pprod-tipo',
                    'field' => 'tipo_produto',
                    'options' => Product::tiposProduto(),
                    'allowEmpty' => false,
                ])
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-marca">
                <label for="pprod-marca">F2 | Marca</label>
                <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                    @include('filament.components.erp.produtos.form.compact-select', [
                        'id' => 'pprod-marca',
                        'field' => 'marca',
                        'options' => $this->marcaOptions,
                    ])
                    <button
                        type="button"
                        data-erp-open-lookup="marca"
                        class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                        title="Cadastrar / gerenciar Marca"
                        aria-label="Cadastrar Marca"
                    >
                        <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </button>
                </div>
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-grupo">
                <label for="pprod-grupo">F2 | Grupo</label>
                <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                    @include('filament.components.erp.produtos.form.grupo-select', [
                        'id' => 'pprod-grupo',
                    ])
                    <button
                        type="button"
                        data-erp-open-lookup="grupo"
                        class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                        title="Cadastrar / gerenciar Grupo"
                        aria-label="Cadastrar Grupo"
                    >
                        <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </button>
                </div>
            </div>

            <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-unidade">
                <label for="pprod-unidade">F2 | Unidade</label>
                <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                    @include('filament.components.erp.produtos.form.compact-select', [
                        'id' => 'pprod-unidade',
                        'field' => 'unidade',
                        'options' => collect($this->unidadeOptions)
                            ->mapWithKeys(fn (string $label, string $sigla): array => [
                                $sigla => \App\Models\Unidade::optionLabel($sigla, $label),
                            ])
                            ->all(),
                    ])
                    <button
                        type="button"
                        data-erp-open-lookup="unidade"
                        class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                        title="Cadastrar / gerenciar Unidade"
                        aria-label="Cadastrar Unidade"
                    >
                        <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="erp-produtos-form__row-precos-info">
    <section class="erp-produtos-form__section erp-produtos-form__section--precos">
        <div class="erp-produtos-form__section-head">
            <h3 class="erp-produtos-form__section-title">Preços e logística</h3>
            <button
                type="button"
                class="erp-produtos-form__btn-precificar"
                wire:click="openProductPrecificacao"
                title="Abrir tela de precificação (custo + varejo / atacado / especial)"
            >
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <path d="M8 7h8M8 11h8M8 15h3"/>
                </svg>
                <span>Precificar</span>
            </button>
        </div>
        <div class="erp-produtos-form__grid erp-produtos-form__grid--r3">
            <div class="erp-produtos-form__cell erp-produtos-form__cell--preco-venda erp-produtos-form__cell--preco-nivel erp-produtos-form__cell--preco-varejo erp-produtos-form__cell--required">
                <label for="pprod-preco-venda">Pr. Varejo</label>
                <input
                    id="pprod-preco-venda"
                    type="text"
                    wire:model.blur="data.preco_venda"
                    data-mask="money-br"
                    data-erp-prod-enter
                    class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--preco-venda"
                    title="Preço varejo (nível 1) — use Precificar para custo e margens"
                >
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--preco-nivel erp-produtos-form__cell--preco-atacado">
                <label for="pprod-preco-atacado">Pr. Atacado</label>
                <input
                    id="pprod-preco-atacado"
                    type="text"
                    wire:model="data.preco_atacado"
                    data-mask="money-br"
                    data-erp-prod-enter
                    class="erp-pcad-form__input erp-produtos-form__input--num"
                    title="Preço atacado (nível 2)"
                >
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--preco-nivel erp-produtos-form__cell--preco-especial">
                <label for="pprod-preco-especial">Pr. Especial</label>
                <input
                    id="pprod-preco-especial"
                    type="text"
                    wire:model="data.preco_especial"
                    data-mask="money-br"
                    data-erp-prod-enter
                    class="erp-pcad-form__input erp-produtos-form__input--num"
                    title="Preço especial (nível 3)"
                >
            </div>
            <div class="erp-produtos-form__cell">
                <label for="pprod-qtd-atacado">Qtd. Atacado</label>
                <input id="pprod-qtd-atacado" type="text" wire:model="data.qtd_atacado" data-mask="integer" inputmode="numeric" data-erp-prod-enter class="erp-pcad-form__input erp-produtos-form__input--num">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--validade">
                <label for="pprod-validade">Validade</label>
                <div class="erp-prod-date-wrap">
                    <input
                        id="pprod-validade"
                        type="date"
                        wire:model.blur="data.validade"
                        data-erp-native-date
                        data-erp-prod-enter
                        class="erp-pcad-form__input erp-produtos-form__input--validade erp-pcad-form__input--date"
                        onclick="try{this.showPicker()}catch(e){}"
                    >
                    <span class="erp-prod-date-icon" aria-hidden="true"></span>
                </div>
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--lote">
                <label for="pprod-lote">Lote</label>
                <input
                    id="pprod-lote"
                    type="text"
                    wire:model.blur="data.lote"
                    maxlength="60"
                    data-erp-prod-enter
                    class="erp-pcad-form__input erp-produtos-form__input--lote"
                    title="Número / identificação do lote"
                >
            </div>
        </div>
    </section>

    <section class="erp-produtos-form__section erp-produtos-form__section--info-adic">
        <h3 class="erp-produtos-form__section-title">Informações adicionais</h3>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--info-adic">
            <textarea
                id="pprod-info-adicionais"
                wire:model.blur="data.info_adicionais"
                rows="2"
                maxlength="100"
                aria-label="Informações adicionais"
                class="erp-pcad-form__textarea erp-produtos-form__textarea--info-adic"
                autocomplete="off"
            ></textarea>
        </div>
    </section>
    </div>

    <section class="erp-produtos-form__section erp-produtos-form__section--estoque">
        <h3 class="erp-produtos-form__section-title">Cadastro e fiscal</h3>
        <div class="erp-produtos-form__grid erp-produtos-form__grid--r5">
            <div class="erp-produtos-form__cell erp-produtos-form__cell--est-min">
                <label for="pprod-est-min">Estoque Mínimo</label>
                <input id="pprod-est-min" type="text" wire:model="data.estoque_minimo" data-mask="integer" inputmode="numeric" data-erp-prod-enter class="erp-pcad-form__input erp-produtos-form__input--num">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--peso">
                <label for="pprod-peso">Peso (KG)</label>
                <input id="pprod-peso" type="text" wire:model="data.peso_kg" data-mask="decimal3" data-erp-prod-enter class="erp-pcad-form__input erp-produtos-form__input--num">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--ncm">
                <label for="pprod-ncm">NCM</label>
                <input id="pprod-ncm" type="text" wire:model="data.ncm" wire:blur="syncNcmDescricaoFromCodigo" data-mask="digits" data-max-digits="8" maxlength="8" data-erp-prod-enter class="erp-pcad-form__input erp-produtos-form__input--ncm">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--ncm-desc">
                <label for="pprod-ncm-desc" class="erp-produtos-form__label--blank">&nbsp;</label>
                <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                    <input id="pprod-ncm-desc" type="text" wire:model="data.ncm_descricao" readonly class="erp-pcad-form__input erp-produtos-form__input--ncm-desc">
                    <button type="button" data-erp-open-lookup="ncm" class="erp-pcad-form__btn erp-pcad-form__btn--icon" title="F2 — Pesquisar NCM">
                        <span class="erp-pcad-form__btn-icon">🔍</span>
                    </button>
                </div>
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--cest">
                <label for="pprod-cest">CEST</label>
                <input id="pprod-cest" type="text" wire:model="data.cest" data-mask="digits" data-max-digits="7" maxlength="7" data-erp-prod-enter class="erp-pcad-form__input erp-produtos-form__input--cest">
            </div>
        </div>
    </section>
</div>
