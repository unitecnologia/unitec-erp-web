@php
    $statusOptions = [
        'todos' => 'TODOS',
        'importado' => 'IMPORTADOS',
        'erro' => 'COM ERRO',
    ];
@endphp

<div class="erp-entregadores" wire:ignore.self>
    <div class="erp-entregadores__locate">
        <span class="erp-entregadores__locate-label">Situação</span>
        <div class="erp-entregadores__locate-controls">
            <select wire:model.live="statusFilter" class="erp-entregadores__select">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
