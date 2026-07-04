<div class="erp-nfe__total">
    <div class="erp-nfe__chave-group erp-nfe__chave-group--footer">
        <label class="erp-nfe__chave-label">
            CHAVE NFC-e
            <input
                type="text"
                readonly
                value="{{ $this->highlightedChave }}"
                class="erp-nfe__input erp-nfe__chave-input erp-nfe__chave-input--readonly"
                tabindex="-1"
            >
        </label>
    </div>
    <span class="erp-nfe__total-label">TOTAL DE NFC-E</span>
    <span class="erp-nfe__total-value">
        R$ {{ number_format($this->filteredTotal, 2, ',', '.') }}
    </span>
</div>
