@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'recebi_de' => 'NOMINAL',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div
    class="erp-recibos"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if (! $wire.showForm) {
            $event.preventDefault();
            $wire.handleRecibosEscape();
        }
    "
    x-on:erp-focus-recibos-search.window="$el.querySelector('.erp-recibos__input')?.focus()"
>
    <div class="erp-recibos__filter-block">
        <div class="erp-recibos__period">
            <label class="erp-recibos__period-label">
                de
                <input
                    type="date"
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    class="erp-recibos__period-input erp-recibos__period-from"
                >
            </label>
            <label class="erp-recibos__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    class="erp-recibos__period-input"
                >
            </label>
            <button
                type="button"
                wire:click="applyPeriodFilter"
                onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-recibos') ?? document)"
                class="erp-recibos__btn"
            >
                Filtrar Período
            </button>
        </div>

        <div class="erp-recibos__locate-group">
            <span class="erp-recibos__locate-label">F12 | Localizar</span>
            <select wire:model.live="searchColumn" class="erp-recibos__select erp-recibos__locate-field">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input
                type="text"
                wire:model="localSearch"
                wire:keydown.enter="search"
                wire:key="recibos-local-search-{{ $this->searchColumn }}"
                class="erp-recibos__input erp-recibos__locate-input"
                placeholder="Digite para pesquisar"
                autocomplete="off"
                @if ($this->searchColumn === 'recebi_de') data-erp-uppercase @endif
                @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
            >
            <button type="button" wire:click="search" class="erp-recibos__btn">Pesquisa</button>
            <button type="button" wire:click="clearSearch" class="erp-recibos__btn erp-recibos__btn--secondary">Limpar</button>
        </div>

        <div class="erp-recibos__page-size-group">
            <label class="erp-recibos__page-size-label">
                por página
                <select wire:model.live="tableRecordsPerPage" class="erp-recibos__select erp-recibos__page-size-select">
                    @foreach ($pageSizeOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
