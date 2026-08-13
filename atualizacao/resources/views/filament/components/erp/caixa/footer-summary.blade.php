<div class="erp-caixa__summary">
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

    <span class="erp-caixa__summary-label erp-caixa__summary-label--saldo">SALDO |</span>
    <span class="erp-caixa__summary-value erp-caixa__summary-value--saldo">
        {{ number_format($this->saldoAtual, 2, ',', '.') }}
    </span>
</div>
