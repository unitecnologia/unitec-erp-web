@php
    $situacaoFilters = [
        'todos' => 'Todos',
        'a_pagar' => 'À Pagar',
        'atrasadas' => 'Atrasadas',
        'pagas' => 'Pagas',
    ];

    $situacaoAtual = filled($this->situacaoFilter) ? (string) $this->situacaoFilter : 'todos';
@endphp

<div class="erp-pagar__footer">
    <div class="erp-pagar__totals">
        <div class="erp-pagar__total-item">
            <span class="erp-pagar__total-label">TOTAL À PAGAR |</span>
            <span class="erp-pagar__total-value">
                R$ {{ number_format($this->totalAPagar, 2, ',', '.') }}
            </span>
        </div>
        <div class="erp-pagar__total-item">
            <span class="erp-pagar__total-label">TOTAL PAGO |</span>
            <span class="erp-pagar__total-value erp-pagar__total-value--paid">
                R$ {{ number_format($this->totalPago, 2, ',', '.') }}
            </span>
        </div>
    </div>

    <div class="erp-pagar__footer-filters">
        <div
            class="erp-pagar__filter-group"
            role="group"
            aria-label="Situação"
            wire:ignore
            x-data="{ statusAtivo: @js($situacaoAtual) }"
        >
            <span class="erp-pagar__filter-group-label">Situação</span>
            <div class="erp-pagar__filter-segment">
                @foreach ($situacaoFilters as $value => $label)
                    <button
                        type="button"
                        class="erp-pagar__filter-chip"
                        :class="{ 'erp-pagar__filter-chip--active': statusAtivo === @js($value) }"
                        :aria-pressed="statusAtivo === @js($value) ? 'true' : 'false'"
                        @click="statusAtivo = @js($value); $wire.setSituacaoFilter(@js($value))"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
