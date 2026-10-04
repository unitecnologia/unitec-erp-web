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

    $situacaoAtual = filled($this->situacaoFilter) ? (string) $this->situacaoFilter : 'todos';
    $contagensSituacao = $this->contagensSituacao;
    $formaAtual = filled($this->formaFilter) ? (string) $this->formaFilter : 'todos';
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
        <div class="erp-receber__total-item">
            <span class="erp-receber__total-label">TOTAL ATRASADO |</span>
            <span class="erp-receber__total-value erp-receber__total-value--late">
                R$ {{ number_format($this->totalAtrasado, 2, ',', '.') }}
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
        <div
            class="erp-receber__filter-group"
            role="group"
            aria-label="Situação"
            wire:ignore
            x-data="{ statusAtivo: @js($situacaoAtual) }"
        >
            <span class="erp-receber__filter-group-label">Situação</span>
            <div class="erp-receber__filter-segment">
                @foreach ($situacaoFilters as $value => $label)
                    <button
                        type="button"
                        class="erp-receber__filter-chip"
                        :class="{ 'erp-receber__filter-chip--active': statusAtivo === @js($value) }"
                        :aria-pressed="statusAtivo === @js($value) ? 'true' : 'false'"
                        @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.conta-receber-list-table') || [])[0]; if (t) t.call('setSituacaoFilter', @js($value))"
                    >{{ $label }} <span class="erp-receber__filter-chip-count" data-situacao="{{ $value }}">({{ (int) ($contagensSituacao[$value] ?? 0) }})</span></button>
                @endforeach
            </div>
        </div>

        <div
            class="erp-receber__filter-group"
            role="group"
            aria-label="Forma"
            wire:ignore
            x-data="{ statusAtivo: @js($formaAtual) }"
        >
            <span class="erp-receber__filter-group-label">Forma</span>
            <div class="erp-receber__filter-segment">
                @foreach ($formaFilters as $value => $label)
                    <button
                        type="button"
                        class="erp-receber__filter-chip"
                        :class="{ 'erp-receber__filter-chip--active': statusAtivo === @js($value) }"
                        :aria-pressed="statusAtivo === @js($value) ? 'true' : 'false'"
                        @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.conta-receber-list-table') || [])[0]; if (t) t.call('setFormaFilter', @js($value))"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
