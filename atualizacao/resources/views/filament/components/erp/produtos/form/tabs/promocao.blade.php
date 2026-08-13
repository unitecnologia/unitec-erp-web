<div class="erp-produtos-promocao">
    <fieldset class="erp-produtos-tab-group">
        <legend class="erp-produtos-tab-group__legend">Promoção</legend>
        <div class="erp-produtos-tab-group__fields">
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-promo-inicio">Data início</label>
                <div class="erp-prod-date-wrap">
                    <input
                        id="pprod-promo-inicio"
                        type="date"
                        wire:model.blur="data.promo_data_inicio"
                        data-erp-native-date
                        class="erp-pcad-form__input erp-produtos-promocao__input--date erp-pcad-form__input--date"
                        onclick="try{this.showPicker()}catch(e){}"
                    >
                    <span class="erp-prod-date-icon" aria-hidden="true"></span>
                </div>
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-promo-fim">Data fim</label>
                <div class="erp-prod-date-wrap">
                    <input
                        id="pprod-promo-fim"
                        type="date"
                        wire:model.blur="data.promo_data_fim"
                        data-erp-native-date
                        class="erp-pcad-form__input erp-produtos-promocao__input--date erp-pcad-form__input--date"
                        onclick="try{this.showPicker()}catch(e){}"
                    >
                    <span class="erp-prod-date-icon" aria-hidden="true"></span>
                </div>
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-promo-varejo">Pr. Varejo</label>
                <input
                    id="pprod-promo-varejo"
                    type="text"
                    wire:model="data.promo_preco_venda"
                    data-mask="money-br"
                    class="erp-pcad-form__input erp-produtos-promocao__input--money"
                >
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-promo-atacado">Pr. Atacado</label>
                <input
                    id="pprod-promo-atacado"
                    type="text"
                    wire:model="data.promo_preco_atacado"
                    data-mask="money-br"
                    class="erp-pcad-form__input erp-produtos-promocao__input--money"
                >
            </div>
        </div>
    </fieldset>
</div>
