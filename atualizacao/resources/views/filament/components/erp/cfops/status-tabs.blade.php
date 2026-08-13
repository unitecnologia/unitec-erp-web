@php
    $statusTabs = [
        'todos' => 'Todos',
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
    ];
@endphp

<div class="erp-cfop__status-wrap">
    <div class="erp-cfop__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class([
                    'erp-cfop__tab',
                    'erp-cfop__tab--active' => $this->statusFilter === $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
