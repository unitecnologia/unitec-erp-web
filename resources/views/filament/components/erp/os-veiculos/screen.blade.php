@php
    $searchFields = [
        'placa' => 'PLACA',
        'descricao' => 'DESCRIÇÃO',
        'marca' => 'MARCA',
        'modelo' => 'MODELO',
        'chassi' => 'CHASSI',
        'renavam' => 'RENAVAM',
        'cor' => 'COR',
        'cidade' => 'CIDADE',
    ];
    $pageSizeOptions = [25, 50, 100];
    $activeSearchFields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];
    $searchButtonLabel = implode(' + ', array_map(
        fn (string $key): string => $searchFields[$key] ?? $key,
        $activeSearchFields,
    ));
@endphp

<div class="erp-veiculos" wire:ignore.self>
    <div class="erp-veiculos__filters">
        <div class="erp-veiculos__filters-row">
            <div class="erp-veiculos__search-group">
                <span class="erp-veiculos__locate-label">F6 | Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                    'markedFields' => $activeSearchFields,
                    'buttonLabel' => $searchButtonLabel,
                    'closeOnSelect' => false,
                ])
                <input
                    type="text"
                    wire:model.live.debounce.350ms="localSearch"
                    wire:key="os-veiculos-local-search-{{ implode('-', $activeSearchFields) }}"
                    class="erp-veiculos__input erp-veiculos__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    data-erp-uppercase
                >
            </div>

            <div class="erp-veiculos__page-size-group">
                <label class="erp-veiculos__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-veiculos__select erp-veiculos__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="erp-veiculos__hint">Clique na tecla [DELETE] para excluir veículo.</p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
