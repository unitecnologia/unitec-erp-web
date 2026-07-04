<div class="erp-pcad-form erp-config-fiscais-form">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="cfg-email-host">Host SMTP</label>
        <input id="cfg-email-host" type="text" wire:model="form.email_host" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-email-porta">Porta</label>
        <input id="cfg-email-porta" type="text" wire:model="form.email_porta" class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="cfg-email-user">Usuário</label>
        <input id="cfg-email-user" type="text" wire:model="form.email_user" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-email-pass">Senha</label>
        <input
            id="cfg-email-pass"
            type="password"
            wire:model="form.email_senha"
            class="erp-pcad-form__input erp-pcad-form__input--grow"
            autocomplete="off"
            placeholder="Deixe em branco para manter"
        >
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="cfg-email-assunto">Assunto padrão</label>
        <input id="cfg-email-assunto" type="text" wire:model="form.email_assunto" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="form.email_ssl">
            <span>SSL</span>
        </label>
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="form.email_tls">
            <span>TLS</span>
        </label>
    </div>
</div>
