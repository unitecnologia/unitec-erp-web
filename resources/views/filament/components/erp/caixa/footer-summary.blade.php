@php
    $temMov = (bool) ($this->caixaRodape['tem_movimento'] ?? false);
    $aberto = (bool) ($this->caixaRodape['status_aberto'] ?? false);
    $contado = $this->caixaRodape['valor_contado'] ?? null;
    $dif = $this->caixaRodape['diferenca'] ?? null;
@endphp
<div class="erp-caixa__summary">
    @if ($temMov)
        <span class="erp-caixa__summary-label">TROCO INICIAL |</span>
        <span class="erp-caixa__summary-value" title="Conferência operacional — não é lançamento financeiro">
            {{ number_format((float) ($this->caixaRodape['troco_inicial'] ?? 0), 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label">ENTRADA |</span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--entrada">
            {{ number_format((float) ($this->caixaRodape['entradas'] ?? 0), 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label">SAÍDA |</span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--saida">
            {{ number_format((float) ($this->caixaRodape['saidas'] ?? 0), 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label erp-caixa__summary-label--saldo" title="Troco + entradas − saídas do movimento">
            SALDO OPERACIONAL |
        </span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--saldo" title="Troco + entradas − saídas do movimento">
            {{ number_format((float) ($this->caixaRodape['saldo_movimento'] ?? 0), 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label" title="Saldo contábil dos lançamentos (sem troco de conferência)">
            SALDO FINANCEIRO |
        </span>
        <span class="erp-caixa__summary-value" title="Saldo contábil dos lançamentos (sem troco de conferência)">
            {{ number_format((float) ($this->caixaRodape['saldo_financeiro'] ?? 0), 2, ',', '.') }}
        </span>

        @if (! $aberto && $contado !== null)
            <span class="erp-caixa__summary-label">CONTADO |</span>
            <span class="erp-caixa__summary-value">
                {{ number_format((float) $contado, 2, ',', '.') }}
            </span>
        @endif

        @if (! $aberto && $dif !== null)
            <span class="erp-caixa__summary-label">DIFERENÇA |</span>
            <span class="erp-caixa__summary-value {{ abs((float) $dif) >= 0.01 ? 'erp-caixa__summary-value--saida' : '' }}">
                {{ number_format((float) $dif, 2, ',', '.') }}
            </span>
        @endif
    @else
        <span class="erp-caixa__summary-label">SALDO ANTERIOR |</span>
        <span class="erp-caixa__summary-value">
            {{ number_format($this->saldoAnterior, 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label">ENTRADA |</span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--entrada">
            {{ number_format($this->totalEntrada, 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label">SAÍDA |</span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--saida">
            {{ number_format($this->totalSaida, 2, ',', '.') }}
        </span>

        <span class="erp-caixa__summary-label erp-caixa__summary-label--saldo">SALDO FINANCEIRO |</span>
        <span class="erp-caixa__summary-value erp-caixa__summary-value--saldo">
            {{ number_format($this->saldoAtual, 2, ',', '.') }}
        </span>
    @endif
</div>
