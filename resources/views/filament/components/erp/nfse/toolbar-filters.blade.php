@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'data_emissao' => 'DT. EMISSÃO',
        'competencia' => 'COMPETÊNCIA',
        'tomador' => 'TOMADOR',
        'chave' => 'CHAVE',
        'protocolo' => 'PROTOCOLO',
        'municipio' => 'MUNICÍPIO',
        'total' => 'TOTAL',
    ];

    $activeFields = count($this->searchFieldsActive) >= 2
        ? array_slice($this->searchFieldsActive, 0, 2)
        : ['tomador', 'data_emissao'];
    $searchButtonLabel = collect($activeFields)
        ->map(fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'))
        ->implode(' + ');
    $dateFields = ['data_emissao', 'competencia'];
    $hasDateSearch = collect($activeFields)->contains(
        fn (string $column): bool => in_array($column, $dateFields, true)
    );
    $placeholders = [
        'numero' => 'Digite o número',
        'tomador' => 'DIGITE O TOMADOR',
        'chave' => 'Digite a chave',
        'protocolo' => 'Digite o protocolo',
        'municipio' => 'Digite o município',
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
            @if ($fieldKey === 'tomador')
                <span class="erp-nfe__locate-search-field erp-nfe__locate-search-field--cliente">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="localSearchByField.tomador"
                        wire:keydown.enter="search"
                        wire:key="nfse-local-search-tomador"
                        class="erp-nfe__input erp-nfe__search-text erp-nfe__search-text--cliente"
                        placeholder="{{ $placeholders['tomador'] }}"
                        autocomplete="off"
                        data-erp-uppercase
                    >
                </span>
            @else
                <input
                    type="text"
                    wire:model.live.debounce.300ms="localSearchByField.{{ $fieldKey }}"
                    wire:keydown.enter="search"
                    wire:key="nfse-local-search-{{ $fieldKey }}"
                    class="erp-nfe__input erp-nfe__search-text"
                    placeholder="{{ $placeholders[$fieldKey] ?? 'Digite para pesquisar' }}"
                    autocomplete="off"
                    @if ($fieldKey === 'chave') inputmode="numeric" maxlength="50" @endif
                >
            @endif
        @endforeach

        @if ($hasDateSearch)
            <div
                class="erp-nfe__search-date-range"
                wire:key="nfse-local-search-dates-{{ implode('-', $activeFields) }}"
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
