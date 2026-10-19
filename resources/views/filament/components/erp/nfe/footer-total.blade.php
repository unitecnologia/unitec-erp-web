@php
    $totalCanceladas = (float) $this->filteredTotalCanceladas;
    $totalLiquido = round((float) $this->filteredTotal - $totalCanceladas, 2);
@endphp
<div class="erp-nfe__total">
    <span class="erp-nfe__total-label">TOTAL DE NF-E</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--bruto">
        R$ {{ number_format($this->filteredTotal, 2, ',', '.') }}
    </span>
    <span class="erp-nfe__total-label erp-nfe__total-label--canceladas">TOTAL CANCELADAS</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--canceladas">
        R$ {{ number_format($totalCanceladas, 2, ',', '.') }}
    </span>
    <span class="erp-nfe__total-label erp-nfe__total-label--liquido">TOTAL LÍQUIDO</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--liquido">
        R$ {{ number_format($totalLiquido, 2, ',', '.') }}
    </span>
</div>
