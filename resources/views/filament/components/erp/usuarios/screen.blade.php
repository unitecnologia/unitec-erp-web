@php
    $searchFields = [
        'name' => 'NOME',
        'email' => 'E-MAIL',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-usuarios" wire:ignore.self>
    <div class="erp-usuarios__filters">
        <div class="erp-usuarios__filters-row">
            <div class="erp-usuarios__search-group">
                <span class="erp-usuarios__locate-label">F6 | Localizar</span>
                <select wire:model.live="searchColumn" class="erp-usuarios__select erp-usuarios__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="usuarios-local-search-{{ $this->searchColumn }}"
                    class="erp-usuarios__input erp-usuarios__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($this->searchColumn === 'name') data-erp-uppercase @endif
                >
            </div>

            <div class="erp-usuarios__search-actions">
                <button type="button" wire:click="search" class="erp-usuarios__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-usuarios__btn erp-usuarios__btn--secondary">Limpar</button>
            </div>

            <div class="erp-usuarios__page-size-group">
                <label class="erp-usuarios__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-usuarios__select erp-usuarios__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="erp-usuarios__hint">
        Clique na tecla [DELETE] para excluir usuário.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
