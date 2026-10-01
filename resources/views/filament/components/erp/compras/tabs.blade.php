@php
    $statusTabs = [
        'todas' => 'Todas',
        'aberta' => 'Aberta',
        'fechada' => 'Fechada',
        'cancelada' => 'Cancelada',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todas';
@endphp

<div
    class="erp-compras__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-compras__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="erp-compras__tab"
                :class="{ 'erp-compras__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.compra-list-table') || [])[0]; if (t) t.call('setStatusFilter', @js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
