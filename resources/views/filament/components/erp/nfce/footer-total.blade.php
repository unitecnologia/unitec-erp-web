@php
    $aviso = $this->highlightedNfceAvisoRodape;
@endphp
<div class="erp-nfe__total erp-nfe__total--nfce">
    <div class="erp-nfe__chave-group erp-nfe__chave-group--footer">
        <label class="erp-nfe__chave-label">
            CHAVE NFC-e
            <input
                type="text"
                readonly
                data-erp-locked
                value="{{ $this->highlightedChave }}"
                class="erp-nfe__input erp-nfe__chave-input erp-nfe__chave-input--readonly"
                tabindex="-1"
                title="Chave da NFC-e selecionada (somente leitura)"
                onclick="this.select()"
            >
        </label>
    </div>

    @if ($aviso)
        <div class="erp-nfce__contingencia-aviso" role="status">
            <strong class="erp-nfce__contingencia-aviso__titulo">{{ $aviso['titulo'] }}</strong>
            <span class="erp-nfce__contingencia-aviso__motivo">Motivo: {{ $aviso['motivo'] }}</span>
            <span class="erp-nfce__contingencia-aviso__ajuda">{{ $aviso['ajuda'] }}</span>
        </div>
    @endif

    <div class="erp-nfe__total-summary erp-nfe__total-summary--nfce-verde">
        <span class="erp-nfe__total-label">TOTAL DE NFC-E</span>
        <span class="erp-nfe__total-value">
            R$ {{ number_format($this->filteredTotal, 2, ',', '.') }}
        </span>
    </div>
</div>
