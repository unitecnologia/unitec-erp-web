@php
    $aviso = $this->highlightedNfceAvisoRodape;
    $totalCanceladas = (float) $this->filteredTotalCanceladas;
    $totalLiquido = round((float) $this->filteredTotal - $totalCanceladas, 2);
@endphp
<div class="erp-nfe__total erp-nfe__total--nfce">
    <div class="erp-nfe__total-summary erp-nfe__total-summary--nfce-totais">
        <span class="erp-nfe__total-label">TOTAL DE NFC-E</span>
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

    @if ($aviso)
        <div class="erp-nfce__contingencia-aviso" role="status">
            <strong class="erp-nfce__contingencia-aviso__titulo">{{ $aviso['titulo'] }}</strong>
            <span class="erp-nfce__contingencia-aviso__motivo">Motivo: {{ $aviso['motivo'] }}</span>
            <span class="erp-nfce__contingencia-aviso__ajuda">{{ $aviso['ajuda'] }}</span>
        </div>
    @endif
</div>
