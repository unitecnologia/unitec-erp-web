<div class="erp-pcad-form erp-terminais-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-sat-tipo">Tipo</label>
        <input id="term-sat-tipo" type="text" wire:model="data.tipo_sat_dll" class="erp-pcad-form__input erp-pcad-form__input--sm">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="term-sat-modelo">Versão / Modelo</label>
        <input id="term-sat-modelo" type="text" wire:model="data.modelo_sat_dll" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="term-sat-dll">Caminho da DLL SAT</label>
        <input id="term-sat-dll" type="text" wire:model="data.caminho_sat_dll" class="erp-pcad-form__input">
    </div>

    <div class="erp-terminais-form__actions">
        <button type="button" wire:click="moduleStubSatTest" class="erp-terminais-form__test-btn">Testar SAT</button>
    </div>

    <p class="erp-terminais-form__stub">Integração SAT/MFE via ACBr permanece stub no ambiente web.</p>
</div>
