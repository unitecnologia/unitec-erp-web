@php
    $searchFields = [
        'produto' => 'PRODUTO',
        'codigo' => 'CÓDIGO',
        'data' => 'DATA',
    ];
@endphp

<div class="erp-ajustes-estoque" wire:ignore.self>
    <div class="erp-ajustes-estoque__filter-block">
        <span class="erp-ajustes-estoque__filter-title">F5 | Localizar</span>

        <label class="erp-ajustes-estoque__period-check">
            <input type="checkbox" wire:model.live="informarPeriodo">
            Informar Período
        </label>

        <div class="erp-ajustes-estoque__period">
            <label class="erp-ajustes-estoque__period-label">
                Período de
                <input type="date" data-wire-field="periodoDe" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input" @disabled(! $this->informarPeriodo)>
            </label>
            <label class="erp-ajustes-estoque__period-label">
                até
                <input type="date" data-wire-field="periodoAte" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input" @disabled(! $this->informarPeriodo)>
            </label>
            <button type="button" wire:click="applyPeriodFilter" onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-ajustes-estoque') ?? document)" class="erp-ajustes-estoque__btn" @disabled(! $this->informarPeriodo)>Filtrar Período</button>
        </div>
    </div>

    <div class="erp-ajustes-estoque__locate">
        <div class="erp-ajustes-estoque__locate-controls">
            <select wire:model.live="searchColumn" class="erp-ajustes-estoque__select">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="localSearch" wire:key="ajustes-estoque-search-{{ $this->searchColumn }}" class="erp-ajustes-estoque__input" placeholder="Selecione uma das colunas e pesquise aqui...">
        </div>
    </div>

    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
