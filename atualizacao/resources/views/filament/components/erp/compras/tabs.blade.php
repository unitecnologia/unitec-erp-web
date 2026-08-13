@php
    $statusTabs = [
        'todas' => 'Todas',
        'aberta' => 'Aberta',
        'fechada' => 'Fechada',
        'cancelada' => 'Cancelada',
    ];
@endphp

<div class="erp-compras__tabs-wrap">
    <div class="erp-compras__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class(['erp-compras__tab', 'erp-compras__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
