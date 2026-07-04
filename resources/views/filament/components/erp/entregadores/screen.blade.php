@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'NOME',
    ];
@endphp

<div class="erp-entregadores" wire:ignore.self>
    <div class="erp-entregadores__locate">
        <span class="erp-entregadores__locate-label">F6 | Localizar</span>
        <div class="erp-entregadores__locate-controls">
            <select wire:model.live="searchColumn" class="erp-entregadores__select">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input
                type="text"
                wire:model.live.debounce.300ms="localSearch"
                wire:key="entregadores-local-search-{{ $this->searchColumn }}"
                class="erp-entregadores__input"
                placeholder="DIGITE AQUI SUA PESQUISA"
            >
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
