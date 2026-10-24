@php
    $tipoTabs = [
        'pedido' => 'Pedidos',
        'nfce' => 'NFC-e',
        'nfe' => 'NF-e',
        'nfse' => 'NFS-e',
        'todos' => 'Todos',
    ];

    $statusSummaryOrder = [
        'todos' => 'TODOS',
        'aberto' => 'ABERTO',
        'gravado' => 'GRAVADO',
        'fechado' => 'FECHADO',
        'cancelado' => 'CANCELADO',
    ];

    $tipoAtual = filled($this->tipoFilter) ? (string) $this->tipoFilter : 'todos';
    if ($tipoAtual === 'cupom') {
        $tipoAtual = 'todos';
    }
    $ui = $this->vendaIndicatorsUi;
    $tipoCounts = $ui['tipoCounts'] ?? [];
    $statusValues = $ui['statusValues'] ?? [];
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
            >
                <span>{{ $label }}</span>
                <span class="erp-vendas__type-tab-count" data-venda-tipo-count="{{ $value }}">{{ $tipoCounts[$value] ?? '(0)' }}</span>
            </button>
        @endforeach
    </div>

    <div class="erp-vendas__totals-strip" wire:ignore>
        <div class="erp-vendas__totals">
            @foreach ($statusSummaryOrder as $value => $label)
                <div class="erp-vendas__total-item">
                    <span class="erp-vendas__total-label">{{ $label }}</span>
                    <span
                        class="erp-vendas__total-value erp-vendas__total-value--{{ $value }}"
                        data-venda-status-value="{{ $value }}"
                    >{{ $statusValues[$value] ?? 'R$ 0,00' }}</span>
                </div>
            @endforeach
        </div>

        <div class="erp-vendas__total">
            <span class="erp-vendas__total-label">TOTAL</span>
            <span class="erp-vendas__total-count" data-venda-total-count>{{ $ui['totalCount'] ?? '0 vendas' }}</span>
            <span class="erp-vendas__total-value" data-venda-total-value>{{ $ui['totalValue'] ?? 'R$ 0,00' }}</span>
        </div>
    </div>
</div>
