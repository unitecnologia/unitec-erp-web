@php
    $totais = $this->nfseListagemTotaisFormatados;
@endphp
<div class="erp-nfe__total">
    <span class="erp-nfe__total-label">TOTAL DE NFS-E</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--bruto">
        R$ {{ $totais['bruto'] }}
    </span>
    <span class="erp-nfe__total-label erp-nfe__total-label--canceladas">TOTAL CANCELADAS</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--canceladas">
        R$ {{ $totais['canceladas'] }}
    </span>
    <span class="erp-nfe__total-label erp-nfe__total-label--liquido">TOTAL LÍQUIDO</span>
    <span class="erp-nfe__total-value erp-nfe__total-value--liquido">
        R$ {{ $totais['liquido'] }}
    </span>
</div>
