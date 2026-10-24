@php
    $statusTabs = [
        'todos' => 'Todos',
        'aberto' => 'Aberto',
        'gravado' => 'Gravado',
        'fechado' => 'Fechado',
        'cancelado' => 'Cancelado',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todos';
    $statusCounts = $this->vendaIndicatorsUi['statusCounts'] ?? [];
@endphp

<div
    class="erp-vendas__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
    x-on:erp-vendas-status-changed.window="statusAtivo = $event.detail || 'todos'"
>
    <div class="erp-vendas__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="erp-vendas__tab"
                :class="{ 'erp-vendas__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); window.ErpVendasStatusFilter ? window.ErpVendasStatusFilter.set(@js($value)) : ((window.Livewire?.getByName?.('erp.venda-list-table') || [])[0]?.call('setStatusFilter', @js($value)))"
            >
                <span>{{ $label }}</span>
                <span class="erp-vendas__tab-count" data-venda-status-count="{{ $value }}">{{ $statusCounts[$value] ?? '(0)' }}</span>
            </button>
        @endforeach
    </div>
</div>
