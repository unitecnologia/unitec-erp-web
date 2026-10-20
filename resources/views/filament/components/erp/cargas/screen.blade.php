@php
    $statusOptions = [
        'todos' => 'Todos',
        'aberta' => 'Aberta',
        'fechada' => 'Fechada',
        'cancelada' => 'Cancelada',
    ];
    $pageSizeOptions = [25, 50, 100];
    $periodoDeIso = filled($this->periodoDe) ? $this->periodoDe : '';
    $periodoAteIso = filled($this->periodoAte) ? $this->periodoAte : '';
    $periodoDeValor = filled($periodoDeIso)
        ? \Illuminate\Support\Carbon::parse($periodoDeIso)->format('d/m/Y')
        : '';
    $periodoAteValor = filled($periodoAteIso)
        ? \Illuminate\Support\Carbon::parse($periodoAteIso)->format('d/m/Y')
        : '';
    $searchFields = \App\Support\Erp\Carga\CargaListPreferences::searchFieldLabels();
    $activeSearchFields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];
    $searchButtonLabel = implode(' + ', array_map(
        fn (string $key): string => $searchFields[$key] ?? $key,
        $activeSearchFields,
    ));
    $numericSearch = $activeSearchFields !== [] && array_diff($activeSearchFields, ['numero', 'pedidos_count']) === [];
    $decimalSearch = $activeSearchFields !== [] && array_diff($activeSearchFields, ['valor_total']) === [];
@endphp

<div class="erp-cargas" wire:ignore.self>
    <div class="erp-cargas__filters">
        <div class="erp-cargas__filters-row">
            <span class="erp-cargas__locate-label">Carga / Romaneio</span>

            <div class="erp-cargas__search-group">
                <span class="erp-cargas__search-label">Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                    'markedFields' => $activeSearchFields,
                    'buttonLabel' => $searchButtonLabel,
                    'closeOnSelect' => false,
                ])
                <input
                    type="text"
                    wire:model.live.debounce.300ms="localSearch"
                    wire:key="cargas-local-search-{{ implode('-', $activeSearchFields) }}"
                    class="erp-cargas__input erp-cargas__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($numericSearch) inputmode="numeric" @endif
                    @if ($decimalSearch) inputmode="decimal" @endif
                >
            </div>

            <div
                class="erp-cargas__period-group"
                wire:ignore
                data-erp-date-group
                data-erp-date-auto-apply="1"
                data-erp-date-apply-method="applyPeriodFilterAuto"
            >
                <label class="erp-cargas__period-label">
                    Período de
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoDeIso }}"
                        value="{{ $periodoDeValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-cargas__period-input erp-cargas__period-from erp-date-input"
                        aria-label="Período inicial"
                    >
                </label>
                <label class="erp-cargas__period-label">
                    até
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoAteIso }}"
                        value="{{ $periodoAteValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-cargas__period-input erp-date-input"
                        aria-label="Período final"
                    >
                </label>
            </div>

            <label class="erp-cargas__field">
                <span class="erp-cargas__field-label">Status</span>
                <select wire:model.live="statusFilter" class="erp-cargas__select erp-cargas__status-select">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="erp-cargas__page-size-group">
                <label class="erp-cargas__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-cargas__select erp-cargas__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
