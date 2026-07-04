@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'DESCRIÇÃO',
    ];
@endphp

<div class="erp-grupos" wire:ignore.self>
    <div class="erp-grupos__locate">
        <span class="erp-grupos__locate-label">F6 | Localizar</span>
        <div class="erp-grupos__locate-controls">
            <select wire:model.live="searchColumn" class="erp-grupos__select">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="localSearch" wire:key="grupos-local-search-{{ $this->searchColumn }}" class="erp-grupos__input">
        </div>
    </div>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
