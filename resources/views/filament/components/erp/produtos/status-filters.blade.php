@php
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];
@endphp

@if (! $this->isSeriaisView())
    <div class="erp-produtos__status-wrap">
        <div class="erp-produtos__status">
            @foreach ($statusTabs as $value => $label)
                <button
                    type="button"
                    wire:click="setStatusFilter('{{ $value }}')"
                    @class(['erp-produtos__tab', 'erp-produtos__tab--active' => $this->statusFilter === $value])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>
@endif
