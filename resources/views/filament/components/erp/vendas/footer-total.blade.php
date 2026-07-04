@php
    $tipoTabs = [
        'pedido' => 'Pedidos',
        'cupom' => 'Cupom',
        'todos' => 'Todos',
    ];
@endphp

<div class="erp-vendas__footer">
    <div class="erp-vendas__type-tabs">
        @foreach ($tipoTabs as $value => $label)
            <button
                type="button"
                wire:click="setTipoFilter('{{ $value }}')"
                @class(['erp-vendas__type-tab', 'erp-vendas__type-tab--active' => $this->tipoFilter === $value])
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
