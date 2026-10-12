@php
    use App\Models\Venda;
    use App\Support\Erp\ErpTimezone;

    $searchFields = [
        'numero' => 'NÚMERO',
        'data' => 'DATA',
        'cliente' => 'CLIENTE',
        'vendedor' => 'VENDEDOR',
        'plataforma' => 'PLATAFORMA',
        'meio_pagamento' => 'MEIO DE PAGAMENTO',
        'total' => 'TOTAL',
        'situacao' => 'SITUAÇÃO',
        'tipo' => 'TIPO',
        'hora' => 'HORA',
    ];

    $activeFields = array_values(array_unique(array_filter(
        [$this->searchColumn, $this->searchColumn2],
        fn (string $column): bool => $column !== '',
    )));
    $fieldModels = [];
    foreach ($activeFields as $index => $column) {
        $fieldModels[$column] = $index === 0 ? 'localSearch' : 'localSearch2';
    }
    $searchButtonLabel = implode(' + ', array_map(
        fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'),
        $activeFields,
    ));

    $hojeIso = ErpTimezone::toLocal()->toDateString();
    $localSearchDeIso = filled($this->localSearchDe) ? $this->localSearchDe : $hojeIso;
    $localSearchAteIso = filled($this->localSearchAte) ? $this->localSearchAte : $hojeIso;

    $selectOptions = [
        'plataforma' => ['TODAS', collect(Venda::plataformaLabels())->map(fn ($label) => mb_strtoupper($label, 'UTF-8'))->all()],
        'meio_pagamento' => ['TODOS', array_combine($this->meioPagamentoFilterOptions, $this->meioPagamentoFilterOptions) ?: []],
        'situacao' => ['TODOS', collect(Venda::statusLabels())->map(fn ($label) => mb_strtoupper($label, 'UTF-8'))->all()],
        'tipo' => ['TODOS', collect(Venda::tipoLabels())->map(fn ($label) => mb_strtoupper($label, 'UTF-8'))->all()],
    ];
    $placeholders = [
        'numero' => 'Digite o número',
        'cliente' => 'Digite o cliente',
        'vendedor' => 'Digite o vendedor',
        'total' => 'Digite o total',
    ];
@endphp

<div @class([
    'erp-vendas__search-group',
    'erp-vendas__search-group--dual' => count($activeFields) > 1,
])>
    <span class="erp-vendas__locate-label">F8 | Localizar</span>
    @include('filament.components.erp.shared.search-field-dropdown', [
        'fields' => $searchFields,
        'searchColumn' => $this->searchColumn,
        'markedFields' => $activeFields,
        'buttonLabel' => $searchButtonLabel,
        'wireMethod' => 'toggleSearchField',
        'closeOnSelect' => false,
    ])

    @foreach ($activeFields as $column)
        @php $model = $fieldModels[$column]; @endphp

        @if ($column === 'data')
            <div
                class="erp-vendas__search-date-range"
                data-erp-date-group
                data-erp-date-auto-apply="1"
                data-erp-date-apply-method="applyDateFilterAuto"
                wire:ignore
                wire:key="vendas-local-search-dates-{{ $localSearchDeIso }}-{{ $localSearchAteIso }}"
            >
                <label class="erp-vendas__period-label">
                    de
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $localSearchDeIso }}"
                        value="{{ $localSearchDeIso }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-vendas__period-input erp-vendas__search-date-from"
                    >
                </label>
                <label class="erp-vendas__period-label">
                    até
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $localSearchAteIso }}"
                        value="{{ $localSearchAteIso }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-vendas__period-input erp-vendas__search-date-to"
                    >
                </label>
            </div>
        @elseif ($column === 'hora')
            <div class="erp-vendas__search-time-range" wire:key="vendas-local-search-times">
                <label class="erp-vendas__period-label">
                    hora inicial
                    <input
                        type="time"
                        wire:model.live.debounce.600ms="localSearchHoraDe"
                        class="erp-vendas__period-input erp-vendas__search-time-from"
                    >
                </label>
                <label class="erp-vendas__period-label">
                    hora fim
                    <input
                        type="time"
                        wire:model.live.debounce.600ms="localSearchHoraAte"
                        class="erp-vendas__period-input erp-vendas__search-time-to"
                    >
                </label>
            </div>
        @elseif (isset($selectOptions[$column]))
            @php [$emptyLabel, $options] = $selectOptions[$column]; @endphp
            <select
                wire:model.live="{{ $model }}"
                wire:key="vendas-local-search-{{ $model }}-{{ $column }}"
                class="erp-vendas__select erp-vendas__search-value-select"
            >
                <option value="">{{ $emptyLabel }}</option>
                @foreach ($options as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @else
            <input
                type="text"
                wire:model.live.debounce.450ms="{{ $model }}"
                wire:keydown.enter="search"
                wire:key="vendas-local-search-{{ $model }}-{{ $column }}"
                class="erp-vendas__input erp-vendas__search-text"
                placeholder="{{ $placeholders[$column] ?? 'Digite para pesquisar' }}"
                autocomplete="off"
                @if (in_array($column, ['cliente', 'vendedor'], true)) data-erp-uppercase @endif
            >
        @endif
    @endforeach
</div>
