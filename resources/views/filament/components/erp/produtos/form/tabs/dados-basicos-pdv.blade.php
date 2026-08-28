@php
    use App\Models\Product;
@endphp

<div class="erp-pcad-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-codigo">Código</label>
        <input
            id="pprod-codigo"
            type="text"
            value="{{ $this->data['codigo'] ?? '' }}"
            disabled
            tabindex="-1"
            aria-readonly="true"
            class="erp-pcad-form__input erp-pcad-form__input--xs erp-produtos-form__input--codigo erp-produtos-form__input--info"
        >
        <input type="hidden" wire:model="data.codigo">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline erp-pcad-form__label--required" for="pprod-descricao">Descrição</label>
        <input id="pprod-descricao" type="text" wire:model="data.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-barras">Código de Barras</label>
        <input
            id="pprod-barras"
            type="text"
            inputmode="numeric"
            wire:model="data.codigo_barras"
            class="erp-pcad-form__input erp-pcad-form__input--doc"
            name="erp_barcode_main"
            autocomplete="one-time-code"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            data-form-type="other"
            data-bwignore="true"
            readonly
            onfocus="this.removeAttribute('readonly')"
        >
        <button
            type="button"
            data-erp-search-barcode
            wire:loading.attr="disabled"
            wire:target="searchCodigoBarras"
            class="erp-pcad-form__btn"
            title="Pesquisar código de barras"
        >
            <span class="erp-pcad-form__btn-icon" wire:loading.remove wire:target="searchCodigoBarras">🔍</span>
            <span wire:loading wire:target="searchCodigoBarras">Consultando...</span>
            <span wire:loading.remove wire:target="searchCodigoBarras">Pesquisar Barras</span>
        </button>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-barras-caixa">Cód. Barras (Caixa)</label>
        <input
            id="pprod-barras-caixa"
            type="text"
            inputmode="numeric"
            wire:model="data.codigo_barras_caixa"
            wire:blur="normalizeProductBarcodeCaixaOnBlur"
            class="erp-pcad-form__input erp-pcad-form__input--sm"
            name="erp_barcode_caixa"
            autocomplete="one-time-code"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-lpignore="true"
            data-1p-ignore="true"
            data-form-type="other"
            data-bwignore="true"
            readonly
            onfocus="this.removeAttribute('readonly')"
        >
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-referencia">Referência</label>
        <input id="pprod-referencia" type="text" wire:model="data.referencia" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-tipo">Tipo de Produto</label>
        @include('filament.components.erp.produtos.form.compact-select', [
            'id' => 'pprod-tipo',
            'field' => 'tipo_produto',
            'options' => Product::tiposProduto(),
            'allowEmpty' => false,
            'grow' => true,
        ])
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-marca">F2 | Marca</label>
        @include('filament.components.erp.produtos.form.compact-select', [
            'id' => 'pprod-marca',
            'field' => 'marca',
            'options' => $this->marcaOptions,
            'grow' => true,
        ])
        <button type="button" data-erp-open-lookup="marca" class="erp-pcad-form__btn" title="Cadastrar / gerenciar Marca">
            <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            Cadastrar Marca
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-grupo">F2 | Grupo</label>
        @include('filament.components.erp.produtos.form.grupo-select', [
                        'id' => 'pprod-grupo',
                    ])
        <button type="button" data-erp-open-lookup="grupo" class="erp-pcad-form__btn" title="Cadastrar / gerenciar Grupo">
            <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            Cadastrar Grupo
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-unidade">F2 | Unidade</label>
        @include('filament.components.erp.produtos.form.compact-select', [
            'id' => 'pprod-unidade',
            'field' => 'unidade',
            'options' => collect($this->unidadeOptions)
                ->mapWithKeys(fn (string $label, string $sigla): array => [
                    $sigla => \App\Models\Unidade::optionLabel($sigla, $label),
                ])
                ->all(),
        ])
        <button type="button" data-erp-open-lookup="unidade" class="erp-pcad-form__btn" title="Cadastrar / gerenciar Unidade">
            <span class="erp-pcad-form__btn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            Cadastrar Unidade
        </button>
    </div>

    <div class="erp-pcad-form__row erp-produtos-pcad__row-precos">
        <div class="erp-produtos-pcad__preco-cell erp-produtos-pcad__preco-cell--venda">
            <label class="erp-pcad-form__label erp-pcad-form__label--required" for="pprod-preco-venda">Pr. Varejo</label>
            <input
                id="pprod-preco-venda"
                type="text"
                wire:model.blur="data.preco_venda"
                data-mask="money-br"
                class="erp-pcad-form__input erp-pcad-form__input--num erp-produtos-form__input--preco-venda"
                title="Preço varejo (nível 1) — use Precificar para custo e margens"
            >
        </div>
        <div class="erp-produtos-pcad__preco-cell erp-produtos-pcad__preco-cell--atacado">
            <label class="erp-pcad-form__label" for="pprod-preco-atacado">Pr. Atacado</label>
            <input id="pprod-preco-atacado" type="text" wire:model="data.preco_atacado" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num" title="Preço atacado (nível 2)">
        </div>
        <div class="erp-produtos-pcad__preco-cell erp-produtos-pcad__preco-cell--especial">
            <label class="erp-pcad-form__label" for="pprod-preco-especial">Pr. Especial</label>
            <input id="pprod-preco-especial" type="text" wire:model="data.preco_especial" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num" title="Preço especial (nível 3)">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-qtd-atacado">Qtd. Atacado</label>
            <input id="pprod-qtd-atacado" type="text" wire:model="data.qtd_atacado" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell erp-produtos-pcad__preco-cell--acao">
            <label class="erp-pcad-form__label erp-produtos-form__label--blank">&nbsp;</label>
            <button
                type="button"
                class="erp-produtos-form__btn-precificar"
                wire:click="openProductPrecificacao"
                title="Abrir tela de precificação"
            >
                <span>Precificar</span>
            </button>
        </div>
    </div>

    <div class="erp-pcad-form__row erp-pcad-form__row--localizacao">
        @include('filament.components.erp.produtos.form.localizacao-fields')
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-est-min">Estoque Mínimo</label>
        <input id="pprod-est-min" type="text" wire:model="data.estoque_minimo" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-pcad-form__input--num">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-estoque">Estoque Atual</label>
        <input
            id="pprod-estoque"
            type="text"
            wire:model="data.estoque"
            readonly
            tabindex="-1"
            class="erp-pcad-form__input erp-pcad-form__input--num erp-produtos-form__input--readonly"
            title="Somente consulta — altere o estoque pelos movimentos do sistema"
        >
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-peso">Peso (KG)</label>
        <input id="pprod-peso" type="text" wire:model="data.peso_kg" data-mask="decimal3" class="erp-pcad-form__input erp-pcad-form__input--num">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-ncm">NCM</label>
        <input id="pprod-ncm" type="text" wire:model="data.ncm" wire:blur="syncNcmDescricaoFromCodigo" data-mask="digits" data-max-digits="8" maxlength="8" class="erp-pcad-form__input erp-pcad-form__input--ncm">
        <input id="pprod-ncm-desc" type="text" wire:model="data.ncm_descricao" readonly class="erp-pcad-form__input erp-pcad-form__input--grow">
        <button type="button" data-erp-open-lookup="ncm" class="erp-pcad-form__btn" title="F2 — Pesquisar NCM">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar NCM
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-cest">CEST</label>
        <input id="pprod-cest" type="text" wire:model="data.cest" data-mask="digits" data-max-digits="7" maxlength="7" class="erp-pcad-form__input erp-pcad-form__input--cest">
    </div>
</div>
