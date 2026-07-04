<div class="erp-pcad-form erp-terminais-form">
    <fieldset class="erp-terminais-form__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.eh_caixa"> É caixa</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.pdv"> Usa PDV</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.pesquisa_rapida"> Caixa rápido (sem enter)</label>
    </fieldset>

    <fieldset class="erp-terminais-form__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.mostrar_mensagem_pdv"> Mostrar mensagem no PDV</label>
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.mostrar_tela_caixa_livre"> Tela caixa livre</label>
    </fieldset>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-msg-pdv">Mensagem PDV</label>
        <input id="term-msg-pdv" type="text" wire:model="data.mensagem_pdv" class="erp-pcad-form__input">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-time-livre">Tempo tela livre (s)</label>
        <input id="term-time-livre" type="text" wire:model="data.time_tela_caixa_livre" data-mask="integer" class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <p class="erp-terminais-form__hint">Os botões F3 Vendedor e F4 Busca Avançada do PDV são controlados em Empresa &gt; Parâmetros.</p>
</div>
