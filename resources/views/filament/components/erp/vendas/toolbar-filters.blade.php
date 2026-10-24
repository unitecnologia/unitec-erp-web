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
        'tipo' => 'TIPO',
        'hora' => 'HORA',
    ];

    $activeFields = $this->activeSearchColumns();
    $textFields = array_values(array_filter(
        $activeFields,
        fn (string $column): bool => ! in_array($column, ['data', 'hora'], true),
    ));
    $hasData = in_array('data', $activeFields, true);
    $hasHora = in_array('hora', $activeFields, true);
    $widgetCount = ($hasData ? 1 : 0) + ($hasHora ? 1 : 0) + ($textFields !== [] ? 1 : 0);

    $searchButtonLabel = $activeFields === []
        ? 'CAMPO'
        : implode(' + ', array_map(
            fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'),
            $activeFields,
        ));

    $hojeIso = ErpTimezone::toLocal()->toDateString();
    $localSearchDeIso = filled($this->localSearchDe) ? $this->localSearchDe : $hojeIso;
    $localSearchAteIso = filled($this->localSearchAte) ? $this->localSearchAte : $hojeIso;
    $localSearchDeBr = filled($localSearchDeIso)
        ? \Illuminate\Support\Carbon::parse($localSearchDeIso)->format('d/m/Y')
        : '';
    $localSearchAteBr = filled($localSearchAteIso)
        ? \Illuminate\Support\Carbon::parse($localSearchAteIso)->format('d/m/Y')
        : '';

    $selectOptions = [
        'plataforma' => ['TODAS', collect(Venda::plataformaLabels())->map(fn ($label) => mb_strtoupper($label, 'UTF-8'))->all()],
        'meio_pagamento' => ['TODOS', array_combine($this->meioPagamentoFilterOptions, $this->meioPagamentoFilterOptions) ?: []],
        'tipo' => ['TODOS', collect(Venda::tipoLabels())->map(fn ($label) => mb_strtoupper($label, 'UTF-8'))->all()],
    ];
    $placeholders = [
        'numero' => 'Digite o número',
        'cliente' => 'Digite o cliente',
        'vendedor' => 'Digite o vendedor',
        'total' => 'Digite o total',
    ];

    $singleTextField = count($textFields) === 1 ? $textFields[0] : null;
    $useSelect = $singleTextField !== null && isset($selectOptions[$singleTextField]);
    $textPlaceholder = count($textFields) > 1
        ? 'Digite para pesquisar nos campos marcados'
        : ($placeholders[$singleTextField] ?? 'Digite para pesquisar');

    $statusFilterAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todos';
    $statusFilterOptions = [
        'todos' => 'TODOS',
        Venda::STATUS_ABERTO => 'ABERTO',
        Venda::STATUS_GRAVADO => 'GRAVADO',
        Venda::STATUS_FECHADO => 'FECHADO',
        Venda::STATUS_CANCELADO => 'CANCELADO',
    ];
@endphp

<div @class([
    'erp-vendas__search-group',
    'erp-vendas__search-group--dual' => $widgetCount > 1,
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

    @if ($hasData)
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
                    type="text"
                    data-erp-date
                    data-wire-field="localSearchDe"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $localSearchDeIso }}"
                    value="{{ $localSearchDeBr }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    class="erp-vendas__period-input erp-vendas__search-date-from erp-date-input"
                >
            </label>
            <label class="erp-vendas__period-label">
                até
                <input
                    type="text"
                    data-erp-date
                    data-wire-field="localSearchAte"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $localSearchAteIso }}"
                    value="{{ $localSearchAteBr }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    maxlength="10"
                    class="erp-vendas__period-input erp-vendas__search-date-to erp-date-input"
                >
            </label>
        </div>
    @endif

    <label class="erp-vendas__period-label erp-vendas__status-filter">
        Situação
        <select
            data-erp-vendas-status-filter
            class="erp-vendas__select erp-vendas__status-select"
            aria-label="Situação"
            onchange="window.ErpVendasStatusFilter && window.ErpVendasStatusFilter.set(this.value)"
        >
            @foreach ($statusFilterOptions as $value => $label)
                <option value="{{ $value }}" @selected($statusFilterAtual === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    @if ($hasHora)
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
    @endif

    @if ($textFields !== [])
        @if ($useSelect)
            @php [$emptyLabel, $options] = $selectOptions[$singleTextField]; @endphp
            <select
                wire:model.live="localSearch"
                wire:key="vendas-local-search-select-{{ $singleTextField }}"
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
                wire:model.live.debounce.450ms="localSearch"
                wire:keydown.enter="search"
                wire:key="vendas-local-search-text-{{ implode('-', $textFields) }}"
                class="erp-vendas__input erp-vendas__search-text"
                placeholder="{{ $textPlaceholder }}"
                autocomplete="off"
                @if (array_intersect($textFields, ['cliente', 'vendedor']) !== []) data-erp-uppercase @endif
            >
        @endif
    @endif
</div>
