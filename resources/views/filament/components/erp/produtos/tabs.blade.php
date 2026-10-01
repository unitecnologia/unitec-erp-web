@php
    $viewAtual = $this->isSeriaisView() ? 'seriais' : 'produtos';
@endphp

<div
    class="erp-produtos__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($viewAtual) }"
>
    <div class="erp-produtos__tabs erp-produtos__tabs--view">
        <button
            type="button"
            class="erp-produtos__tab"
            :class="{ 'erp-produtos__tab--active': statusAtivo === 'produtos' }"
            @click="statusAtivo = 'produtos'; const t = (window.Livewire?.getByName?.('erp.product-list-table') || [])[0]; if (t) t.call('setViewFilter', 'produtos')"
        >Produtos</button>
        <button
            type="button"
            class="erp-produtos__tab"
            :class="{ 'erp-produtos__tab--active': statusAtivo === 'seriais' }"
            @click="statusAtivo = 'seriais'; const t = (window.Livewire?.getByName?.('erp.product-list-table') || [])[0]; if (t) t.call('setViewFilter', 'seriais')"
        >Seriais</button>
    </div>
</div>
