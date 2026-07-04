@php
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];
@endphp

<div class="erp-pessoas__status-wrap">
    <div class="erp-pessoas__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class(['erp-pessoas__tab', 'erp-pessoas__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
