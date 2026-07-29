@php
    $situacaoFilters = [
        'todos' => 'Todos',
        'a_pagar' => 'À Pagar',
        'atrasadas' => 'Atrasadas',
        'pagas' => 'Pagas',
    ];
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
        <div class="erp-pagar__filter-group" role="group" aria-label="Situação">
            <span class="erp-pagar__filter-group-label">Situação</span>
            <div class="erp-pagar__filter-segment">
                @foreach ($situacaoFilters as $value => $label)
                    <button
                        type="button"
                        wire:click="setSituacaoFilter('{{ $value }}')"
                        @class(['erp-pagar__filter-chip', 'erp-pagar__filter-chip--active' => $this->situacaoFilter === $value])
                        @if ($this->situacaoFilter === $value) aria-pressed="true" @else aria-pressed="false" @endif
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
