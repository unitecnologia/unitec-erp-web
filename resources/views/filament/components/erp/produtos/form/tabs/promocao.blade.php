<div class="erp-produtos-promocao">
    <fieldset class="erp-produtos-promocao__group">
        <div class="erp-produtos-promocao__fields">
            <div class="erp-produtos-promocao__field">
                <label for="pprod-promo-inicio">Data de inicio</label>
                <input
                    id="pprod-promo-inicio"
                    type="text"
                    wire:model.blur="data.promo_data_inicio"
                    data-mask="date-br"
                    placeholder="dd/mm/aaaa"
                    class="erp-pcad-form__input erp-produtos-promocao__input--date"
                >
            </div>

            <div class="erp-produtos-promocao__field">
                <label for="pprod-promo-fim">Data do Fim</label>
                <input
                    id="pprod-promo-fim"
                    type="text"
                    wire:model.blur="data.promo_data_fim"
                    data-mask="date-br"
                    placeholder="dd/mm/aaaa"
                    class="erp-pcad-form__input erp-produtos-promocao__input--date"
                >
            </div>

            <div class="erp-produtos-promocao__field">
                <label for="pprod-promo-varejo">Preço Venda Varejo</label>
                <input
                    id="pprod-promo-varejo"
                    type="text"
                    wire:model="data.promo_preco_venda"
                    data-mask="money-br"
                    class="erp-pcad-form__input erp-produtos-promocao__input--money"
                >
            </div>

            <div class="erp-produtos-promocao__field">
                <label for="pprod-promo-atacado">Preço Venda Atacado</label>
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
