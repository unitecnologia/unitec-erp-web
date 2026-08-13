<div class="erp-pcad-form erp-terminais-form">
    <fieldset class="erp-terminais-form__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.restaurante"> Restaurante</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.delivery"> Delivery</label>
    </fieldset>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-cozinha">Impressora cozinha</label>
        <input id="term-cozinha" type="text" wire:model="data.caminho_cozinha" class="erp-pcad-form__input">
    </div>
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-bar">Impressora bar</label>
        <input id="term-bar" type="text" wire:model="data.caminho_bar" class="erp-pcad-form__input">
    </div>

    <p class="erp-terminais-form__stub">Módulo mesas/restaurante permanece stub no PDV web.</p>
</div>
