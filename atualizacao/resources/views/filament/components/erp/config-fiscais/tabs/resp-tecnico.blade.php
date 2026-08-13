<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--resp-tecnico">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
        <legend class="erp-pcad__group-title">Responsável técnico (infRespTec)</legend>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            Dados fixos do software UniTecnologia. Obrigatório para autorização de NFC-e/NF-e em Santa Catarina (rejeição 972 quando ausente).
        </p>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-resp-cnpj">CNPJ</label>
            <input
                id="cfg-resp-cnpj"
                type="text"
                wire:model="form.resp_tecnico_cnpj"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                inputmode="numeric"
                maxlength="18"
                readonly
                tabindex="-1"
            >
        </div>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-resp-contato">Contato</label>
            <input
                id="cfg-resp-contato"
                type="text"
                wire:model="form.resp_tecnico_contato"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                maxlength="60"
                readonly
                tabindex="-1"
            >
        </div>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-resp-email">E-mail</label>
            <input
                id="cfg-resp-email"
                type="email"
                wire:model="form.resp_tecnico_email"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                maxlength="60"
                readonly
                tabindex="-1"
            >
        </div>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-resp-fone">Telefone</label>
            <input
                id="cfg-resp-fone"
                type="text"
                wire:model="form.resp_tecnico_fone"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                inputmode="tel"
                maxlength="20"
                readonly
                tabindex="-1"
            >
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
        <legend class="erp-pcad__group-title">CSRT (opcional)</legend>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            Preencha somente se a SEFAZ da UF exigir identificador e hash CSRT no XML.
        </p>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="cfg-resp-id-csrt">ID CSRT</label>
            <input
                id="cfg-resp-id-csrt"
                type="text"
                wire:model="form.resp_tecnico_id_csrt"
                class="erp-pcad-form__input erp-config-fiscais-form__input--token-id"
                maxlength="6"
                placeholder="000001"
            >
            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-resp-csrt">Token CSRT</label>
            <input
                id="cfg-resp-csrt"
                type="text"
                wire:model="form.resp_tecnico_csrt"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                maxlength="100"
                autocomplete="off"
            >
        </div>
    </fieldset>
</div>
