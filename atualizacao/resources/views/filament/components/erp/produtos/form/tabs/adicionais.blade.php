<div class="erp-produtos-adicionais">
    <fieldset class="erp-produtos-tab-group">
        <legend class="erp-produtos-tab-group__legend">Adicionais</legend>
        <div class="erp-produtos-tab-group__fields erp-produtos-tab-group__fields--wrap">
            <label class="erp-pcad__check erp-produtos-tab-group__check">
                <input type="checkbox" wire:model.live="data.is_restaurante">
                <span>Restaurante</span>
            </label>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-tipo-restaurante">Tipo Rest.</label>
                <input id="pprod-tipo-restaurante" type="text" wire:model="data.tipo_restaurante" class="erp-pcad-form__input">
            </div>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-tempo-espera">Espera (min)</label>
                <input id="pprod-tempo-espera" type="text" wire:model="data.tempo_espera" data-mask="integer" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
            </div>
            <label class="erp-pcad__check erp-produtos-tab-group__check">
                <input type="checkbox" wire:model.live="data.is_remedio">
                <span>Remédio</span>
            </label>
            <div class="erp-produtos-tab-group__field">
                <label for="pprod-principio-ativo">Princ. Ativo</label>
                <input id="pprod-principio-ativo" type="text" wire:model="data.principio_ativo_id" data-mask="integer" class="erp-pcad-form__input erp-produtos-child-grid__input-num" @disabled(! ($this->data['is_remedio'] ?? false))>
            </div>
        </div>
    </fieldset>

    @if ($this->data['is_restaurante'] ?? false)
        <fieldset class="erp-produtos-tab-group">
            <legend class="erp-produtos-tab-group__legend">Pizza / Cardápio</legend>
            <div class="erp-produtos-tab-group__fields erp-produtos-tab-group__fields--wrap">
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-menu-id">Menu (ID)</label>
                    <input id="pprod-menu-id" type="text" wire:model="data.menu_id" data-mask="integer" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
                </div>
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-tipo-alimento">Tipo Alim.</label>
                    <input id="pprod-tipo-alimento" type="text" wire:model="data.tipo_alimento" maxlength="1" class="erp-pcad-form__input erp-produtos-child-grid__input-sm">
                </div>
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-qtd-sabores">Qtd. Sabores</label>
                    <input id="pprod-qtd-sabores" type="text" wire:model="data.qtd_sabores" data-mask="integer" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
                </div>
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-valor-pequena">Vl. Pequena</label>
                    <input id="pprod-valor-pequena" type="text" wire:model="data.valor_pequena" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
                </div>
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-valor-media">Vl. Média</label>
                    <input id="pprod-valor-media" type="text" wire:model="data.valor_media" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
                </div>
                <div class="erp-produtos-tab-group__field">
                    <label for="pprod-valor-grande">Vl. Grande</label>
                    <input id="pprod-valor-grande" type="text" wire:model="data.valor_grande" data-mask="decimal3" class="erp-pcad-form__input erp-produtos-child-grid__input-num">
                </div>
            </div>
        </fieldset>
    @endif

    <fieldset class="erp-produtos-tab-group erp-produtos-tab-group--wide">
        <legend class="erp-produtos-tab-group__legend">Textos</legend>
        <div class="erp-produtos-tab-group__fields erp-produtos-tab-group__fields--stack">
            <div class="erp-produtos-tab-group__field erp-produtos-tab-group__field--full">
                <label for="pprod-complemento">Complemento</label>
                <textarea id="pprod-complemento" wire:model="data.complemento" rows="2" class="erp-pcad-form__textarea"></textarea>
            </div>
            <div class="erp-produtos-tab-group__field erp-produtos-tab-group__field--full">
                <label for="pprod-aplicacao">Aplicação / Obs.</label>
                <textarea id="pprod-aplicacao" wire:model="data.aplicacao" rows="2" class="erp-pcad-form__textarea" @disabled(! ($this->data['is_remedio'] ?? false))></textarea>
            </div>
        </div>
    </fieldset>
</div>
