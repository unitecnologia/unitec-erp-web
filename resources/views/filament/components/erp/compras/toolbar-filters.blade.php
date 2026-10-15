@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'data_emissao' => 'DT. EMISSÃO',
        'data_entrada' => 'DT. ENTRADA',
        'numero_nota' => 'Nº DA NOTA',
        'fornecedor' => 'FORNECEDOR',
        'chave' => 'CHAVE',
        'total' => 'TOTAL',
    ];

    $activeFields = $this->searchFieldsActive !== []
        ? $this->searchFieldsActive
        : [$this->searchColumn ?: 'fornecedor'];
    $searchButtonLabel = collect($activeFields)
        ->map(fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'))
        ->implode(' + ');
    $dateFields = ['data_emissao', 'data_entrada'];
    $hasDateSearch = collect($activeFields)->contains(
        fn (string $column): bool => in_array($column, $dateFields, true)
    );
    $placeholders = [
        'numero' => 'Digite o número',
        'numero_nota' => 'Digite o nº da nota',
        'fornecedor' => 'DIGITE O FORNECEDOR',
        'chave' => 'Digite a chave',
        'total' => 'Digite o total',
    ];
@endphp

<div @class([
    'erp-compras__locate',
    'erp-compras__locate--dual' => count($activeFields) > 1,
])>
    <span class="erp-compras__locate-label">F8 | Localizar</span>
    <div class="erp-compras__locate-controls">
        @include('filament.components.erp.shared.search-field-dropdown', [
            'fields' => $searchFields,
            'searchColumn' => $this->searchColumn,
            'markedFields' => $activeFields,
            'buttonLabel' => $searchButtonLabel,
            'wireMethod' => 'setSearchColumn',
            'closeOnSelect' => false,
        ])

        @foreach ($activeFields as $fieldKey)
            @continue(in_array($fieldKey, $dateFields, true))
            <input
                type="text"
                wire:model.live.debounce.250ms="localSearchByField.{{ $fieldKey }}"
                wire:key="compras-local-search-{{ $fieldKey }}"
                class="erp-compras__input erp-compras__search-text"
                placeholder="{{ $placeholders[$fieldKey] ?? 'Digite para pesquisar' }}"
                autocomplete="off"
                @if ($fieldKey === 'fornecedor') data-erp-uppercase @endif
                @if ($fieldKey === 'chave') inputmode="numeric" maxlength="44" @endif
                @if ($fieldKey === 'numero' || $fieldKey === 'numero_nota') inputmode="numeric" @endif
                @if ($fieldKey === 'total') inputmode="decimal" @endif
            >
        @endforeach

        @if ($hasDateSearch)
            <div
                class="erp-compras__search-date-range"
                wire:key="compras-local-search-dates-{{ implode('-', $activeFields) }}"
            >
                <label class="erp-compras__period-label">
                    de
                    <input
                        type="date"
                        wire:model.live="localSearchDe"
                        class="erp-compras__period-input erp-compras__search-date-from"
                    >
                </label>
                <label class="erp-compras__period-label">
                    até
                    <input
                        type="date"
                        wire:model.live="localSearchAte"
                        class="erp-compras__period-input erp-compras__search-date-to"
                    >
                </label>
            </div>
        @endif
    </div>
</div>
