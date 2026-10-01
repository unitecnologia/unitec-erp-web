@php
    use App\Models\OrdemServico;

    $statusTabs = [
        'todos' => 'Todos',
        OrdemServico::SITUACAO_ABERTA => 'Aberta',
        OrdemServico::SITUACAO_FINALIZADA => 'Finalizada',
        OrdemServico::SITUACAO_CANCELADA => 'Cancelada',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todos';
@endphp

<div
    class="erp-orcamentos__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-orcamentos__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                @class([
                    'erp-orcamentos__tab',
                    'erp-orcamentos__tab--todos' => $value === 'todos',
                    'erp-orcamentos__tab--aberto' => $value === OrdemServico::SITUACAO_ABERTA,
                    'erp-orcamentos__tab--fechado' => $value === OrdemServico::SITUACAO_FINALIZADA,
                    'erp-orcamentos__tab--cancelado' => $value === OrdemServico::SITUACAO_CANCELADA,
                ])
                :class="{ 'erp-orcamentos__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
