@php
    $totalLiquido = round((float) $this->filteredTotal - (float) $this->filteredTotalDevolvido, 2);
@endphp
<div class="erp-compras__total">
    <span class="erp-compras__total-label">TOTAL DE COMPRAS</span>
    <span class="erp-compras__total-value erp-compras__total-value--compras">
        R$ {{ number_format($this->filteredTotal, 2, ',', '.') }}
    </span>
    <span class="erp-compras__total-label erp-compras__total-label--devolvido">TOTAL DEVOLVIDO</span>
    <span class="erp-compras__total-value erp-compras__total-value--devolvido">
        R$ {{ number_format($this->filteredTotalDevolvido, 2, ',', '.') }}
    </span>
    <span class="erp-compras__total-label erp-compras__total-label--liquido">TOTAL LÍQUIDO</span>
    <span class="erp-compras__total-value erp-compras__total-value--liquido">
        R$ {{ number_format($totalLiquido, 2, ',', '.') }}
    </span>
</div>
