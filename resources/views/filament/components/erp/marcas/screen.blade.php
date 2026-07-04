@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'DESCRIÇÃO',
    ];
@endphp

<div class="erp-marcas" wire:ignore.self>
    <div class="erp-marcas__locate">
        <span class="erp-marcas__locate-label">F6 | Localizar</span>
        <div class="erp-marcas__locate-controls">
            <select wire:model.live="searchColumn" class="erp-marcas__select">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="localSearch" wire:key="marcas-local-search-{{ $this->searchColumn }}" class="erp-marcas__input">
        </div>
    </div>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
