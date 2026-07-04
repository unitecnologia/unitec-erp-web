@php
    use App\Models\Product;
@endphp

<div class="erp-produtos-form">
    {{-- Linha 1 --}}
    <div class="erp-produtos-form__grid erp-produtos-form__grid--r1">
        <div class="erp-produtos-form__cell erp-produtos-form__cell--codigo">
            <label for="pprod-codigo">Código</label>
            <input id="pprod-codigo" type="text" wire:model="data.codigo" readonly class="erp-pcad-form__input erp-produtos-form__input--codigo">
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--required erp-produtos-form__cell--descricao">
            <label for="pprod-descricao">Descrição</label>
            <input id="pprod-descricao" type="text" wire:model="data.descricao" class="erp-pcad-form__input">
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--barras">
            <label for="pprod-barras">Código de Barras</label>
            <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                <input id="pprod-barras" type="text" wire:model="data.codigo_barras" wire:blur="normalizeProductBarcodeOnBlur" class="erp-pcad-form__input" autocomplete="off">
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
            <input id="pprod-barras-caixa" type="text" wire:model="data.codigo_barras_caixa" wire:blur="normalizeProductBarcodeCaixaOnBlur" class="erp-pcad-form__input" autocomplete="off">
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--referencia">
            <label for="pprod-referencia">Referência</label>
            <input id="pprod-referencia" type="text" wire:model="data.referencia" class="erp-pcad-form__input">
        </div>
    </div>

    {{-- Linha 2 --}}
    <div class="erp-produtos-form__grid erp-produtos-form__grid--r2">
        <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-tipo">
            <label for="pprod-tipo">Tipo de Produto</label>
            <select id="pprod-tipo" wire:model="data.tipo_produto" class="erp-pcad-form__select">
                @foreach (Product::tiposProduto() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-marca">
            <label for="pprod-marca">F2 | Marca</label>
            <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                <select id="pprod-marca" wire:model.live="data.marca" class="erp-pcad-form__select">
                    <option value=""></option>
                    @foreach ($this->marcaOptions as $marca)
                        <option value="{{ $marca }}">{{ $marca }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    data-erp-open-lookup="marca"
                    class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                    title="F2 — Pesquisar Marca"
                >
                    <span class="erp-pcad-form__btn-icon">🔍</span>
                </button>
            </div>
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-grupo">
            <label for="pprod-grupo">F2 | Grupo</label>
            <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                <select id="pprod-grupo" wire:model.live="data.grupo" class="erp-pcad-form__select">
                    <option value=""></option>
                    @foreach ($this->grupoOptions as $grupo)
                        <option value="{{ $grupo }}">{{ $grupo }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    data-erp-open-lookup="grupo"
                    class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                    title="F2 — Pesquisar Grupo"
                >
                    <span class="erp-pcad-form__btn-icon">🔍</span>
                </button>
            </div>
        </div>

        <div class="erp-produtos-form__cell erp-produtos-form__cell--r2-unidade">
            <label for="pprod-unidade">F2 | Unidade</label>
            <div class="erp-produtos-form__control erp-produtos-form__control--lookup">
                <select id="pprod-unidade" wire:model.live="data.unidade" class="erp-pcad-form__select">
                    <option value=""></option>
                    @foreach ($this->unidadeOptions as $sigla => $label)
                        <option value="{{ $sigla }}">{{ $sigla }} — {{ $label }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    data-erp-open-lookup="unidade"
                    class="erp-pcad-form__btn erp-pcad-form__btn--icon"
                    title="F2 — Pesquisar Unidade"
                >
                    <span class="erp-pcad-form__btn-icon">🔍</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Linha 3 --}}
    <div class="erp-produtos-form__grid erp-produtos-form__grid--r3">
        <div class="erp-produtos-form__cell">
            <label for="pprod-preco-compra">Preço Compra</label>
            <input id="pprod-preco-compra" type="text" wire:model.blur="data.preco_compra" data-mask="money-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-pct-custos">% Custos</label>
            <input id="pprod-pct-custos" type="text" wire:model.blur="data.pct_custos" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-preco-custo">Preço Custo</label>
            <input id="pprod-preco-custo" type="text" wire:model.blur="data.preco_custo" data-mask="money-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-pct-lucro">% Lucro</label>
            <input id="pprod-pct-lucro" type="text" wire:model.blur="data.pct_lucro" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--preco-venda erp-produtos-form__cell--required">
            <label for="pprod-preco-venda">Preço Venda</label>
            <input id="pprod-preco-venda" type="text" wire:model.blur="data.preco_venda" data-mask="money-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-qtd-atacado">Qtd. Atacado</label>
            <input id="pprod-qtd-atacado" type="text" wire:model="data.qtd_atacado" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-preco-atacado">Pr. Atacado</label>
            <input id="pprod-preco-atacado" type="text" wire:model="data.preco_atacado" data-mask="money-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-comissao">Comissão %</label>
            <input id="pprod-comissao" type="text" wire:model="data.comissao_pct" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-desconto">Desconto %</label>
            <input id="pprod-desconto" type="text" wire:model="data.desconto_pct" data-mask="percent-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-preco-venda-prazo">Pr.Ven.Prazo</label>
            <input id="pprod-preco-venda-prazo" type="text" wire:model="data.preco_venda_prazo" data-mask="money-br" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-validade">Validade</label>
            <input id="pprod-validade" type="text" wire:model.blur="data.validade" data-wire-field="data.validade" data-mask="date-br" placeholder="dd/mm/aaaa" class="erp-pcad-form__input erp-produtos-form__input--validade">
        </div>
        <div class="erp-produtos-form__cell">
            <label for="pprod-localizacao">Localização</label>
            <input id="pprod-localizacao" type="text" wire:model="data.localizacao" class="erp-pcad-form__input">
        </div>
    </div>

    {{-- Linha 5 --}}
    <div class="erp-produtos-form__grid erp-produtos-form__grid--r5">
        <div class="erp-produtos-form__cell erp-produtos-form__cell--est-min">
            <label for="pprod-est-min">Estoque Mínimo</label>
            <input id="pprod-est-min" type="text" wire:model="data.estoque_minimo" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--est-inicial">
            <label for="pprod-est-inicial">Estoque Inicial</label>
            <input id="pprod-est-inicial" type="text" wire:model="data.estoque_inicial" wire:blur="syncEstoqueFromInicialOnBlur" data-mask="integer" inputmode="numeric" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--estoque">
            <label for="pprod-estoque">Estoque Atual</label>
            <input id="pprod-estoque" type="text" wire:model="data.estoque" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--peso">
            <label for="pprod-peso">Peso (KG)</label>
            <input id="pprod-peso" type="text" wire:model="data.peso_kg" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-form__input--num">
        </div>
        <div class="erp-produtos-form__cell erp-produtos-form__cell--ncm">
            <label for="pprod-ncm">NCM</label>
            <input id="pprod-ncm" type="text" wire:model="data.ncm" wire:blur="syncNcmDescricaoFromCodigo" data-mask="digits" data-max-digits="8" maxlength="8" class="erp-pcad-form__input erp-produtos-form__input--ncm">
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
            <input id="pprod-cest" type="text" wire:model="data.cest" data-mask="digits" data-max-digits="7" maxlength="7" class="erp-pcad-form__input erp-produtos-form__input--cest">
        </div>
    </div>

    @include('filament.components.erp.produtos.form.reservas-ativas')

    @if ($this->isEditingProduct())
        <div class="erp-produtos-form__grid erp-produtos-form__grid--r6 erp-produtos-form__grid--r6-compact">
            <div class="erp-produtos-form__cell erp-produtos-form__cell--e-medio">
                <label for="pprod-e-medio">Estoque Médio (R$)</label>
                <input id="pprod-e-medio" type="text" value="{{ $this->data['e_medio'] ?? '0,000' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--ult-compra">
                <label for="pprod-ult-compra">Última Compra (R$)</label>
                <input id="pprod-ult-compra" type="text" value="{{ $this->data['ult_compra'] ?? '0,00' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
            </div>
            <div class="erp-produtos-form__cell erp-produtos-form__cell--ult-compra-ant">
                <label for="pprod-ult-compra-ant">Últ. Compra Anterior (R$)</label>
                <input id="pprod-ult-compra-ant" type="text" value="{{ $this->data['ult_compra_anterior'] ?? '0,00' }}" readonly class="erp-pcad-form__input erp-produtos-form__input--num erp-produtos-form__input--readonly">
            </div>
        </div>
    @endif
</div>
