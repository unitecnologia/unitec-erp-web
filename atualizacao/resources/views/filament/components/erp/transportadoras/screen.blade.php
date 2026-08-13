@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'proprietario' => 'NOME/RAZÃO',
        'apelido' => 'APELIDO',
        'cnpj_cpf' => 'CPF/CNPJ',
        'cidade' => 'CIDADE',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-transportadoras" wire:ignore.self>
    <div class="erp-transportadoras__filters">
        <div class="erp-transportadoras__filters-row">
            <div class="erp-transportadoras__search-group">
                <span class="erp-transportadoras__locate-label">F6 | Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                ])
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="transportadoras-local-search-{{ $this->searchColumn }}"
                    class="erp-transportadoras__input erp-transportadoras__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if (in_array($this->searchColumn, ['proprietario', 'apelido', 'cidade'], true)) data-erp-uppercase @endif
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                >
            </div>

            <div class="erp-transportadoras__search-actions">
                <button type="button" wire:click="search" class="erp-transportadoras__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-transportadoras__btn erp-transportadoras__btn--secondary">Limpar</button>
            </div>

            <div class="erp-transportadoras__page-size-group">
                <label class="erp-transportadoras__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-transportadoras__select erp-transportadoras__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="erp-transportadoras__hint">
        Selecione um registro na grade e use F3 para alterar.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
