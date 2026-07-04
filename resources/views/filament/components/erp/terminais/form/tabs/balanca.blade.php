<div class="erp-pcad-form erp-terminais-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-bal-marca">Balança</label>
        <input id="term-bal-marca" type="text" wire:model="data.balanca_marca" class="erp-pcad-form__input">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-bal-porta">Porta</label>
        <input id="term-bal-porta" type="text" wire:model="data.balanca_porta" class="erp-pcad-form__input">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-bal-vel">Velocidade</label>
        <input id="term-bal-vel" type="text" wire:model="data.balanca_velocidade" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-bal-data">Data bits</label>
        <input id="term-bal-data" type="text" wire:model="data.balanca_databits" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-bal-par">Paridade</label>
        <input id="term-bal-par" type="text" wire:model="data.balanca_paridade" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-bal-stop">Stop bits</label>
        <input id="term-bal-stop" type="text" wire:model="data.balanca_stopbits" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-bal-hand">Handshaking</label>
        <input id="term-bal-hand" type="text" wire:model="data.balanca_handshaking" class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <fieldset class="erp-terminais-form__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.ler_peso"> Habilita leitura Peso no PDV</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.busca_balanca_barras"> Busca código de barras balança</label>
    </fieldset>

    <p class="erp-terminais-form__stub">Leitura serial de balança permanece stub no PDV web; modelo de etiqueta continua nos parâmetros da empresa.</p>
</div>
