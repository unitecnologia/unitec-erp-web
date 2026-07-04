@php
    $statusOptions = [
        'pendentes' => 'PENDENTES',
        'ativos' => 'ATIVOS',
        'revogados' => 'REVOGADOS',
        'todos' => 'TODOS',
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
            <span class="erp-entregadores__hint--top">
                Confira o código no aparelho e use <kbd>F2</kbd> para autorizar ou <kbd>F4</kbd> para revogar.
            </span>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
