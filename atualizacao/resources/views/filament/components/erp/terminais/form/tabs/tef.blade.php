<div class="erp-pcad-form erp-terminais-form">
    <label class="erp-pcad__check erp-terminais-form__usa-tef"><input type="checkbox" wire:model="data.usa_tef"> Usa TEF</label>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Configurações TEF</legend>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="term-tef-ger">Gerenciador TEF</label>
            <input id="term-tef-ger" type="text" wire:model="data.tef_gerenciador" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-tef-modelo">Modelo TEF</label>
            <input id="term-tef-modelo" type="text" wire:model="data.modelo_tef" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">
        </div>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="term-tef-loja">Nº Lógico Estabelecimento</label>
            <input id="term-tef-loja" type="text" wire:model="data.numero_loja" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--sm">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-tef-logico">Nº Lógico Terminal</label>
            <input id="term-tef-logico" type="text" wire:model="data.numero_logico_terminal" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--sm">
        </div>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="term-tef-ip">IP Servidor TEF</label>
            <input id="term-tef-ip" type="text" wire:model="data.ip_servidor_tef" class="erp-pcad-form__input">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-tef-pin">Porta PinPad</label>
            <input id="term-tef-pin" type="text" wire:model="data.porta_pin_pad" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--sm">
        </div>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="term-tef-msg">Mensagem PinPad</label>
            <input id="term-tef-msg" type="text" wire:model="data.mensagem_pin_pad" class="erp-pcad-form__input">
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Parâmetros TEF</legend>
        <fieldset class="erp-terminais-form__checks">
            <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usa_pos"> Usa POS</label>
            <label class="erp-pcad__check"><input type="checkbox" wire:model="data.tef_via_reduzida"> Imprimir Via Reduzida</label>
            <label class="erp-pcad__check"><input type="checkbox" wire:model="data.tef_multiplos_cartoes"> Múltiplos Cartões</label>
        </fieldset>
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="term-tef-max">Máx. cartões</label>
            <input id="term-tef-max" type="text" wire:model="data.tef_max_cartoes" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-tef-troco">Troco máximo</label>
            <input id="term-tef-troco" type="text" wire:model="data.tef_troco_maximo" data-mask="decimal" class="erp-pcad-form__input erp-pcad-form__input--sm">
        </div>
        <div class="erp-terminais-form__actions">
            <button type="button" wire:click="moduleStubTefTest" class="erp-terminais-form__test-btn">Testar TEF</button>
        </div>
    </fieldset>

    <p class="erp-terminais-form__stub">TEF/PinPad permanece stub no PDV web; campos gravados para paridade com o Delphi.</p>
</div>
