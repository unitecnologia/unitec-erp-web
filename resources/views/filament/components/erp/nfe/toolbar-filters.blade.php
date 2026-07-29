@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'data_emissao' => 'DT. EMISSÃO',
        'data_saida' => 'DT. SAÍDA',
        'cliente' => 'CLIENTE',
        'chave' => 'CHAVE',
        'protocolo' => 'PROTOCOLO',
        'total' => 'TOTAL',
    ];

    $activeFields = count($this->searchFieldsActive) >= 2
        ? array_slice($this->searchFieldsActive, 0, 2)
        : ['cliente', 'data_emissao'];
    $searchButtonLabel = collect($activeFields)
        ->map(fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'))
        ->implode(' + ');
    $dateFields = ['data_emissao', 'data_saida'];
    $hasDateSearch = collect($activeFields)->contains(
        fn (string $column): bool => in_array($column, $dateFields, true)
    );
    $placeholders = [
        'numero' => 'Digite o número',
        'cliente' => 'DIGITE O CLIENTE',
        'chave' => 'Digite a chave',
        'protocolo' => 'Digite o protocolo',
        'total' => 'Digite o total',
    ];
@endphp

<div class="erp-nfe__locate">
    <span class="erp-nfe__locate-label">Localizar</span>
    <div class="erp-nfe__locate-controls">
        @include('filament.components.erp.shared.search-field-dropdown', [
            'fields' => $searchFields,
            'searchColumn' => $this->searchColumn,
            'markedFields' => $activeFields,
            'buttonLabel' => $searchButtonLabel,
            'wireMethod' => 'toggleSearchField',
            'closeOnSelect' => false,
        ])

        @foreach ($activeFields as $fieldKey)
            @continue(in_array($fieldKey, $dateFields, true))
            @if ($fieldKey === 'cliente')
                <span class="erp-nfe__locate-search-field erp-nfe__locate-search-field--cliente">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="localSearchByField.cliente"
                        wire:key="nfe-local-search-cliente"
                        wire:focus="openLocalClienteLookup"
                        wire:keydown.arrow-up.prevent="moveLocalClienteSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveLocalClienteSelection(1)"
                        wire:keydown.enter.prevent="handleLocalClienteEnter"
                        wire:keydown.escape.prevent="closeLocalClienteLookup"
                        class="erp-nfe__input erp-nfe__search-text erp-nfe__search-text--cliente"
                        placeholder="{{ $placeholders['cliente'] }}"
                        autocomplete="off"
                        data-erp-uppercase
                    >
                    @if ($this->localClienteLookupOpen && filled($this->localSearchByField['cliente'] ?? null))
                        @if ($this->localClienteResults !== [])
                            @include('filament.components.erp.shared.local-cliente-lookup-panel')
                        @else
                            <div class="erp-cliente-filter-lookup erp-cliente-filter-lookup--empty">
                                Nenhum cliente encontrado.
                            </div>
                        @endif
                    @endif
                </span>
            @else
                <input
                    type="text"
                    wire:model.live.debounce.300ms="localSearchByField.{{ $fieldKey }}"
                    wire:keydown.enter="search"
                    wire:key="nfe-local-search-{{ $fieldKey }}"
                    class="erp-nfe__input erp-nfe__search-text"
                    placeholder="{{ $placeholders[$fieldKey] ?? 'Digite para pesquisar' }}"
                    autocomplete="off"
                    @if ($fieldKey === 'chave') inputmode="numeric" maxlength="44" @endif
                >
            @endif
        @endforeach

        @if ($hasDateSearch)
            <div
                class="erp-nfe__search-date-range"
                wire:key="nfe-local-search-dates-{{ implode('-', $activeFields) }}"
            >
                <label class="erp-nfe__period-label">
                    <span class="erp-nfe__period-caption">de</span>
                    <span class="erp-nfe__date-wrap" x-data>
                        <input
                            type="date"
                            data-erp-native-date
                            wire:model.live="localSearchDe"
                            class="erp-nfe__period-input erp-nfe__search-date-from"
                            @click="try { $el.showPicker() } catch (e) {}"
                            @keydown.enter.prevent="try { $el.showPicker() } catch (e) {}"
                        >
                        <span class="erp-nfe__date-icon" aria-hidden="true"></span>
                    </span>
                </label>
                <label class="erp-nfe__period-label">
                    <span class="erp-nfe__period-caption">até</span>
                    <span class="erp-nfe__date-wrap" x-data>
                        <input
                            type="date"
                            data-erp-native-date
                            wire:model.live="localSearchAte"
                            class="erp-nfe__period-input erp-nfe__search-date-to"
                            @click="try { $el.showPicker() } catch (e) {}"
                            @keydown.enter.prevent="try { $el.showPicker() } catch (e) {}"
                        >
                        <span class="erp-nfe__date-icon" aria-hidden="true"></span>
                    </span>
                </label>
            </div>
        @endif
    </div>
</div>
