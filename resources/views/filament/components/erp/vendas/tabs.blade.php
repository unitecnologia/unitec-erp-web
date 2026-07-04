@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'gravado' => 'Gravado',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
    ];
@endphp

<div class="erp-vendas__tabs-wrap">
    <div class="erp-vendas__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class(['erp-vendas__tab', 'erp-vendas__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
