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

    $isDateSearch = in_array($this->searchColumn, ['data_emissao', 'data_entrada'], true);
@endphp

<div class="erp-compras__locate">
    <span class="erp-compras__locate-label">Localizar</span>
    <div class="erp-compras__locate-controls">
        <select wire:model.live="searchColumn" class="erp-compras__select erp-compras__search-field">
            @foreach ($searchFields as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        @if ($isDateSearch)
            <div
                class="erp-compras__search-date-range"
                wire:key="compras-local-search-dates-{{ $this->searchColumn }}"
            >
                <label class="erp-compras__period-label">
                    de
                    <input
                        type="date"
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        class="erp-compras__period-input erp-compras__search-date-from"
                    >
                </label>
                <label class="erp-compras__period-label">
                    até
                    <input
                        type="date"
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
                        class="erp-compras__period-input erp-compras__search-date-to"
                    >
                </label>
            </div>
        @else
            <input
                type="text"
                wire:model.live="localSearch"
                wire:keydown.enter="search"
                wire:key="compras-local-search-{{ $this->searchColumn }}"
                class="erp-compras__input erp-compras__search-text"
                placeholder="Digite para pesquisar"
                autocomplete="off"
                @if (in_array($this->searchColumn, ['fornecedor'], true)) data-erp-uppercase @endif
            >
        @endif
    </div>
</div>
