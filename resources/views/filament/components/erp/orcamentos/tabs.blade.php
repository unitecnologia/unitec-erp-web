@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
        'importado' => 'Importado',
    ];
@endphp

<div class="erp-orcamentos__tabs-wrap erp-list-tabs">
    <div class="erp-orcamentos__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class([
                    'erp-orcamentos__tab',
                    'erp-orcamentos__tab--active' => $this->statusFilter === $value,
                    'erp-orcamentos__tab--todos' => $value === 'todos',
                    'erp-orcamentos__tab--aberto' => $value === 'aberto',
                    'erp-orcamentos__tab--fechado' => $value === 'fechado',
                    'erp-orcamentos__tab--cancelado' => $value === 'cancelado',
                    'erp-orcamentos__tab--importado' => $value === 'importado',
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
