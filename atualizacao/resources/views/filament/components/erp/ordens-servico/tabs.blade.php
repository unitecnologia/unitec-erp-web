@php
    use App\Models\OrdemServico;

    $statusTabs = [
        'todos' => 'Todos',
        OrdemServico::SITUACAO_ABERTA => 'Aberta',
        OrdemServico::SITUACAO_FINALIZADA => 'Finalizada',
        OrdemServico::SITUACAO_CANCELADA => 'Cancelada',
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
                    'erp-orcamentos__tab--aberto' => $value === OrdemServico::SITUACAO_ABERTA,
                    'erp-orcamentos__tab--fechado' => $value === OrdemServico::SITUACAO_FINALIZADA,
                    'erp-orcamentos__tab--cancelado' => $value === OrdemServico::SITUACAO_CANCELADA,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
