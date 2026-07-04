@php
    use App\Models\Product;
@endphp

<div class="erp-pcad-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-codigo">Código</label>
        <input id="pprod-codigo" type="text" wire:model="data.codigo" readonly class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline erp-pcad-form__label--required" for="pprod-descricao">Descrição</label>
        <input id="pprod-descricao" type="text" wire:model="data.descricao" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-barras">Código de Barras</label>
        <input id="pprod-barras" type="text" wire:model="data.codigo_barras" class="erp-pcad-form__input erp-pcad-form__input--doc" autocomplete="off">
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
        <input id="pprod-barras-caixa" type="text" wire:model="data.codigo_barras_caixa" wire:blur="normalizeProductBarcodeCaixaOnBlur" class="erp-pcad-form__input erp-pcad-form__input--sm" autocomplete="off">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-referencia">Referência</label>
        <input id="pprod-referencia" type="text" wire:model="data.referencia" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-tipo">Tipo de Produto</label>
        <select id="pprod-tipo" wire:model="data.tipo_produto" class="erp-pcad-form__select erp-pcad-form__select--md">
            @foreach (Product::tiposProduto() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-marca">F2 | Marca</label>
        <select id="pprod-marca" wire:model.live="data.marca" class="erp-pcad-form__select erp-pcad-form__input--grow">
            <option value=""></option>
            @foreach ($this->marcaOptions as $marca)
                <option value="{{ $marca }}">{{ $marca }}</option>
            @endforeach
        </select>
        <button type="button" data-erp-open-lookup="marca" class="erp-pcad-form__btn" title="F2 — Pesquisar Marca">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar Marca
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-grupo">F2 | Grupo</label>
        <select id="pprod-grupo" wire:model.live="data.grupo" class="erp-pcad-form__select erp-pcad-form__input--grow">
            <option value=""></option>
            @foreach ($this->grupoOptions as $grupo)
                <option value="{{ $grupo }}">{{ $grupo }}</option>
            @endforeach
        </select>
        <button type="button" data-erp-open-lookup="grupo" class="erp-pcad-form__btn" title="F2 — Pesquisar Grupo">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar Grupo
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-unidade">F2 | Unidade</label>
        <select id="pprod-unidade" wire:model.live="data.unidade" class="erp-pcad-form__select erp-pcad-form__input--xs">
            <option value=""></option>
            @foreach ($this->unidadeOptions as $sigla => $label)
                <option value="{{ $sigla }}">{{ $sigla }} — {{ $label }}</option>
            @endforeach
        </select>
        <button type="button" data-erp-open-lookup="unidade" class="erp-pcad-form__btn" title="F2 — Pesquisar Unidade">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar Unidade
        </button>
    </div>

    <div class="erp-pcad-form__row erp-produtos-pcad__row-precos">
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-preco-compra">Preço Compra</label>
            <input id="pprod-preco-compra" type="text" wire:model.blur="data.preco_compra" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-pct-custos">% Custos</label>
            <input id="pprod-pct-custos" type="text" wire:model.blur="data.pct_custos" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-preco-custo">Preço Custo</label>
            <input id="pprod-preco-custo" type="text" wire:model.blur="data.preco_custo" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-pct-lucro">% Lucro</label>
            <input id="pprod-pct-lucro" type="text" wire:model.blur="data.pct_lucro" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell erp-produtos-pcad__preco-cell--venda">
            <label class="erp-pcad-form__label erp-pcad-form__label--required" for="pprod-preco-venda">Preço Venda</label>
            <input id="pprod-preco-venda" type="text" wire:model.blur="data.preco_venda" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-qtd-atacado">Qtd. Atacado</label>
            <input id="pprod-qtd-atacado" type="text" wire:model="data.qtd_atacado" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-preco-atacado">Pr. Atacado</label>
            <input id="pprod-preco-atacado" type="text" wire:model="data.preco_atacado" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-comissao">Comissão %</label>
            <input id="pprod-comissao" type="text" wire:model="data.comissao_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
        <div class="erp-produtos-pcad__preco-cell">
            <label class="erp-pcad-form__label" for="pprod-desconto">Desconto %</label>
            <input id="pprod-desconto" type="text" wire:model="data.desconto_pct" data-mask="percent-br" class="erp-pcad-form__input erp-pcad-form__input--num">
        </div>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-localizacao">Localização</label>
        <input id="pprod-localizacao" type="text" wire:model="data.localizacao" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-validade">Validade</label>
        <input id="pprod-validade" type="text" wire:model.blur="data.validade" data-wire-field="data.validade" data-mask="date-br" placeholder="dd/mm/aaaa" class="erp-pcad-form__input erp-pcad-form__input--validade">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-preco-venda-prazo">Pr.Ven.Prazo</label>
        <input id="pprod-preco-venda-prazo" type="text" wire:model="data.preco_venda_prazo" data-mask="money-br" class="erp-pcad-form__input erp-pcad-form__input--num">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-est-min">Estoque Mínimo</label>
        <input id="pprod-est-min" type="text" wire:model="data.estoque_minimo" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-pcad-form__input--num">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-est-inicial">Estoque Inicial</label>
        <input id="pprod-est-inicial" type="text" wire:model="data.estoque_inicial" wire:blur="syncEstoqueFromInicialOnBlur" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-pcad-form__input--num">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-estoque">Estoque Atual</label>
        <input id="pprod-estoque" type="text" wire:model="data.estoque" data-mask="decimal3" class="erp-pcad-form__input erp-pcad-form__input--num">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pprod-peso">Peso (KG)</label>
        <input id="pprod-peso" type="text" wire:model="data.peso_kg" data-mask="decimal3" class="erp-pcad-form__input erp-pcad-form__input--num">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pprod-ncm">NCM</label>
        <input id="pprod-ncm" type="text" wire:model="data.ncm" data-mask="digits" data-max-digits="8" maxlength="8" class="erp-pcad-form__input erp-pcad-form__input--ncm">
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
