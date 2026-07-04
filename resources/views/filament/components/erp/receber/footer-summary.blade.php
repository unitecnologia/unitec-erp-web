@php
    $situacaoFilters = [
        'todos' => 'Todos',
        'a_receber' => 'À Receber',
        'atrasadas' => 'Atrasadas',
        'recebidas' => 'Recebidas',
    ];

    $formaFilters = [
        'todos' => 'Todos',
        'carteira' => 'Carteira',
        'cheque' => 'Cheques',
        'cartao' => 'Cartão',
        'boleto' => 'Boleto',
    ];
@endphp

<div class="erp-receber__footer">
    <div class="erp-receber__totals">
        <div class="erp-receber__total-item">
            <span class="erp-receber__total-label">TOTAL À RECEBER |</span>
            <span class="erp-receber__total-value">
                R$ {{ number_format($this->totalAReceber, 2, ',', '.') }}
            </span>
        </div>
        <div class="erp-receber__total-item">
            <span class="erp-receber__total-label">TOTAL RECEBIDO |</span>
            <span class="erp-receber__total-value erp-receber__total-value--received">
                R$ {{ number_format($this->totalRecebido, 2, ',', '.') }}
            </span>
        </div>
        @if ($this->quantidadeSelecionada > 0)
            <div class="erp-receber__total-item erp-receber__total-item--selected">
                <span class="erp-receber__total-label">TOTAL SELECIONADO |</span>
                <span class="erp-receber__total-value erp-receber__total-value--selected">
                    R$ {{ number_format($this->totalSelecionado, 2, ',', '.') }}
                </span>
                <span class="erp-receber__total-meta">
                    ({{ $this->quantidadeSelecionada }} {{ $this->quantidadeSelecionada === 1 ? 'conta' : 'contas' }})
                </span>
            </div>
        @endif
    </div>

    <div class="erp-receber__footer-filters">
        <div class="erp-receber__filter-group">
            @foreach ($situacaoFilters as $value => $label)
                <button
                    type="button"
                    wire:click="setSituacaoFilter('{{ $value }}')"
                    @class(['erp-receber__filter-link', 'erp-receber__filter-link--active' => $this->situacaoFilter === $value])
                >{{ $label }}</button>
            @endforeach
        </div>

        <div class="erp-receber__filter-group">
            @foreach ($formaFilters as $value => $label)
                <button
                    type="button"
                    wire:click="setFormaFilter('{{ $value }}')"
                    @class(['erp-receber__filter-link', 'erp-receber__filter-link--active' => $this->formaFilter === $value])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>
</div>
