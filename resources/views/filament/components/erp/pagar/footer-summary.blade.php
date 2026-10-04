@php
    $situacaoFilters = [
        'todos' => 'Todos',
        'a_pagar' => 'À Pagar',
        'atrasadas' => 'Atrasadas',
        'pagas' => 'Pagas',
    ];

    $situacaoAtual = filled($this->situacaoFilter) ? (string) $this->situacaoFilter : 'todos';
    $contagensSituacao = $this->contagensSituacao;
@endphp

<div class="erp-pagar__footer">
    <div class="erp-pagar__totals">
        <div class="erp-pagar__total-item">
            <span class="erp-pagar__total-label">TOTAL À PAGAR |</span>
            <span class="erp-pagar__total-value erp-pagar__total-value--open">
                R$ {{ number_format($this->totalAPagar, 2, ',', '.') }}
            </span>
        </div>
        <div class="erp-pagar__total-item">
            <span class="erp-pagar__total-label">TOTAL PAGO |</span>
            <span class="erp-pagar__total-value erp-pagar__total-value--paid">
                R$ {{ number_format($this->totalPago, 2, ',', '.') }}
            </span>
        </div>
        <div class="erp-pagar__total-item">
            <span class="erp-pagar__total-label">TOTAL ATRASADO |</span>
            <span class="erp-pagar__total-value erp-pagar__total-value--late">
                R$ {{ number_format($this->totalAtrasado, 2, ',', '.') }}
            </span>
        </div>
        @if ($this->quantidadeSelecionada > 0)
            <div class="erp-pagar__total-item erp-pagar__total-item--selected">
                <span class="erp-pagar__total-label">TOTAL SELECIONADO |</span>
                <span class="erp-pagar__total-value erp-pagar__total-value--selected">
                    R$ {{ number_format($this->totalSelecionado, 2, ',', '.') }}
                </span>
                <span class="erp-pagar__total-meta">
                    ({{ $this->quantidadeSelecionada }} {{ $this->quantidadeSelecionada === 1 ? 'conta' : 'contas' }})
                </span>
            </div>
        @endif
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
                    >{{ $label }} <span class="erp-pagar__filter-chip-count" data-situacao="{{ $value }}">({{ (int) ($contagensSituacao[$value] ?? 0) }})</span></button>
                @endforeach
            </div>
        </div>
    </div>
</div>
