@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
        'importado' => 'Importado',
    ];
@endphp

<div class="erp-orcamentos__tabs-wrap">
    <div class="erp-orcamentos__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class(['erp-orcamentos__tab', 'erp-orcamentos__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
