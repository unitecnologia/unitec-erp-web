@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'gravado' => 'Gravado',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todos';
@endphp

<div
    class="erp-vendas__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-vendas__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="erp-vendas__tab"
                :class="{ 'erp-vendas__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.venda-list-table') || [])[0]; if (t) t.call('setStatusFilter', @js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
