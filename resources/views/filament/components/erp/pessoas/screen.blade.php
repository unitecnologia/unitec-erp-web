@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome_razao' => 'RAZÃO/NOME',
        'apelido_fantasia' => 'FANTASIA/APELIDO',
        'cpf_cnpj' => 'CPF/CNPJ',
        'rg_ie' => 'RG/IE',
        'endereco' => 'ENDEREÇO',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-pessoas" wire:ignore.self>
    <div class="erp-pessoas__filters">
        <div class="erp-pessoas__filters-row">
            <div class="erp-pessoas__search-group">
                <span class="erp-pessoas__locate-label">F6 | Localizar</span>
                <select wire:model.live="searchColumn" class="erp-pessoas__select erp-pessoas__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="pessoas-local-search-{{ $this->searchColumn }}-{{ $this->tipoFilter }}"
                    class="erp-pessoas__input erp-pessoas__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if (in_array($this->searchColumn, ['nome_razao', 'apelido_fantasia', 'endereco'], true)) data-erp-uppercase @endif
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                >
            </div>

            <div class="erp-pessoas__search-actions">
                <button type="button" wire:click="search" class="erp-pessoas__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-pessoas__btn erp-pessoas__btn--secondary">Limpar</button>
            </div>

            <div class="erp-pessoas__page-size-group">
                <label class="erp-pessoas__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-pessoas__select erp-pessoas__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.pessoas.tabs')

    <p class="erp-pessoas__hint">
        Clique na tecla [DELETE] para excluir pessoa.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
