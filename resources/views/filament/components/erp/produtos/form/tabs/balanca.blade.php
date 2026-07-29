<div class="erp-produtos-balanca">
    <fieldset class="erp-produtos-tab-group">
        <legend class="erp-produtos-tab-group__legend">Balança</legend>
        <div class="erp-produtos-tab-group__fields erp-produtos-tab-group__fields--inline">
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-prefixo-balanca">Prefixo</label>
                <input
                    id="pprod-prefixo-balanca"
                    type="text"
                    wire:model="data.prefixo_balanca"
                    maxlength="10"
                    class="erp-pcad-form__input"
                    @disabled(! ($this->data['produto_pesado'] ?? false))
                >
            </div>
        </div>
    </fieldset>
</div>
