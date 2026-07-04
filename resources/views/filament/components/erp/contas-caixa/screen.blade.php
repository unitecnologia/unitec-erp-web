@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'DESCRIÇÃO',
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
            <input type="text" wire:model.live.debounce.300ms="localSearch" wire:key="contas-caixa-local-search-{{ $this->searchColumn }}" class="erp-unidades__input">
        </div>
    </div>
    <p class="erp-unidades__hint erp-unidades__hint--top">Você pode mudar a pesquisa clicando no título do campo a ser pesquisado.</p>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
