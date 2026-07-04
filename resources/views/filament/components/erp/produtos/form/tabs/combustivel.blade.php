<div class="erp-produtos-combustivel">
    <fieldset class="erp-produtos-combustivel__group">
        <legend class="erp-produtos-combustivel__legend">Combustível (ANP)</legend>
        <div class="erp-produtos-combustivel__fields">
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-glp">GLP %</label>
                <input id="pprod-glp" type="text" wire:model="data.glp_pct" data-mask="percent-br" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-gnn">GNn %</label>
                <input id="pprod-gnn" type="text" wire:model="data.gnn_pct" data-mask="percent-br" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-gni">GNi %</label>
                <input id="pprod-gni" type="text" wire:model="data.gni_pct" data-mask="percent-br" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-peso-liq">Peso Líq.</label>
                <input id="pprod-peso-liq" type="text" wire:model="data.peso_liq" data-mask="decimal3" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-anp">Cód. ANP</label>
                <input id="pprod-anp" type="text" wire:model="data.anp_code" maxlength="20" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-combustivel__field">
                <label for="pprod-issqn">ISSQN %</label>
                <input id="pprod-issqn" type="text" wire:model="data.issqn" data-mask="percent-br" class="erp-pcad-form__input">
            </div>
        </div>
        <p class="erp-produtos-combustivel__hint">GLP + GNn + GNi deve totalizar 100% quando informado.</p>
    </fieldset>
</div>
