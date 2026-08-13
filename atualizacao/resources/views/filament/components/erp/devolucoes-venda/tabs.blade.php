@php
    use App\Models\DevolucaoVenda;

    $statusTabs = [
        'todos' => 'Todos',
        DevolucaoVenda::SITUACAO_ABERTA => 'Aberta',
        DevolucaoVenda::SITUACAO_FINALIZADA => 'Finalizada',
        DevolucaoVenda::SITUACAO_CANCELADA => 'Cancelada',
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
                    'erp-orcamentos__tab--aberto' => $value === DevolucaoVenda::SITUACAO_ABERTA,
                    'erp-orcamentos__tab--fechado' => $value === DevolucaoVenda::SITUACAO_FINALIZADA,
                    'erp-orcamentos__tab--cancelado' => $value === DevolucaoVenda::SITUACAO_CANCELADA,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
