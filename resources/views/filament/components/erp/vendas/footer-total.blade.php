@php
    $tipoTabs = [
        'pedido' => 'Pedidos',
        'cupom' => 'Cupom',
        'todos' => 'Todos',
    ];

    $tipoAtual = filled($this->tipoFilter) ? (string) $this->tipoFilter : 'todos';
@endphp

<div class="erp-vendas__footer">
    <div
        class="erp-vendas__type-tabs"
        wire:ignore
        x-data="{ statusAtivo: @js($tipoAtual) }"
    >
        @foreach ($tipoTabs as $value => $label)
            <button
                type="button"
                class="erp-vendas__type-tab"
                :class="{ 'erp-vendas__type-tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.venda-list-table') || [])[0]; if (t) t.call('setTipoFilter', @js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="erp-vendas__total">
        <span class="erp-vendas__total-label">TOTAL</span>
        <span class="erp-vendas__total-value">
            R$ {{ number_format($this->filteredTotal, 2, ',', '.') }}
        </span>
    </div>
</div>
