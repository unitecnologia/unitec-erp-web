@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
        'importado' => 'Importado',
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
                    'erp-orcamentos__tab--aberto' => $value === 'aberto',
                    'erp-orcamentos__tab--fechado' => $value === 'fechado',
                    'erp-orcamentos__tab--cancelado' => $value === 'cancelado',
                    'erp-orcamentos__tab--importado' => $value === 'importado',
                ])
                :class="{ 'erp-orcamentos__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.orcamento-list-table') || [])[0]; if (t) t.call('setStatusFilter', @js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
