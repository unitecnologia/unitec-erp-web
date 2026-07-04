@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'NOME',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-vendedores" wire:ignore.self>
    <div class="erp-vendedores__filters">
        <div class="erp-vendedores__filters-row">
            <div class="erp-vendedores__search-group">
                <span class="erp-vendedores__locate-label">F6 | Localizar</span>
                <select wire:model.live="searchColumn" class="erp-vendedores__select erp-vendedores__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="vendedores-local-search-{{ $this->searchColumn }}"
                    class="erp-vendedores__input erp-vendedores__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($this->searchColumn === 'nome') data-erp-uppercase @endif
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                >
            </div>

            <div class="erp-vendedores__search-actions">
                <button type="button" wire:click="search" class="erp-vendedores__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-vendedores__btn erp-vendedores__btn--secondary">Limpar</button>
            </div>

            <div class="erp-vendedores__page-size-group">
                <label class="erp-vendedores__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-vendedores__select erp-vendedores__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="erp-vendedores__hint">
        Clique na tecla [DELETE] para excluir vendedor.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
