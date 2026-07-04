@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'descricao' => 'DESCRIÇÃO',
    ];
@endphp

<div class="erp-unidades" wire:ignore.self>
    <div class="erp-unidades__locate">
        <span class="erp-unidades__locate-label">F6 | Localizar</span>
        <div class="erp-unidades__locate-controls">
            <select wire:model.live="searchColumn" class="erp-unidades__select">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="localSearch" wire:key="formas-pgto-local-search-{{ $this->searchColumn }}" class="erp-unidades__input">
        </div>
    </div>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
