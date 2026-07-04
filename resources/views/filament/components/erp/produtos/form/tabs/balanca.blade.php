<div class="erp-produtos-balanca">
    <fieldset class="erp-produtos-balanca__group">
        <legend class="erp-produtos-balanca__legend">Balança</legend>
        <div class="erp-produtos-balanca__fields">
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model.live="data.produto_pesado">
                <span>Produto Pesado</span>
            </label>
            <div class="erp-produtos-balanca__field">
                <label for="pprod-prefixo-balanca">Prefixo Balança</label>
                <input id="pprod-prefixo-balanca" type="text" wire:model="data.prefixo_balanca" maxlength="10" class="erp-pcad-form__input" @disabled(! ($this->data['produto_pesado'] ?? false))>
            </div>
        </div>
    </fieldset>
</div>
