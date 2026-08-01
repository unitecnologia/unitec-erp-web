@php
    use App\Models\DevolucaoCompra;

    $statusTabs = [
        'todos' => 'Todos',
        DevolucaoCompra::SITUACAO_ABERTA => 'Aberta',
        DevolucaoCompra::SITUACAO_FINALIZADA => 'Finalizada',
        DevolucaoCompra::SITUACAO_CANCELADA => 'Cancelada',
    ];
@endphp

<div class="erp-orcamentos__tabs-wrap">
    <div class="erp-orcamentos__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class([
                    'erp-orcamentos__tab',
                    'erp-orcamentos__tab--active' => $this->statusFilter === $value,
                    'erp-orcamentos__tab--todos' => $value === 'todos',
                    'erp-orcamentos__tab--aberto' => $value === DevolucaoCompra::SITUACAO_ABERTA,
                    'erp-orcamentos__tab--fechado' => $value === DevolucaoCompra::SITUACAO_FINALIZADA,
                    'erp-orcamentos__tab--cancelado' => $value === DevolucaoCompra::SITUACAO_CANCELADA,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
