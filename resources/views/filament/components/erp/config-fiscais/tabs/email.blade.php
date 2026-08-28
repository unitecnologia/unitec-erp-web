<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--email" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__email-group">
        <legend class="erp-pcad__group-title">Envio de e-mail</legend>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            Usado no envio de NF-e, NFC-e e relatórios por e-mail. Grava as alterações com <strong>F2 | Gravar</strong>.
        </p>

        <div class="erp-config-fiscais-form__email-mode">
            <label class="erp-pcad__check">
                <input type="radio" wire:model.live="form.email_modo" value="smtp">
                <span>SMTP</span>
            </label>
            <label class="erp-pcad__check">
                <input type="radio" wire:model.live="form.email_modo" value="api">
                <span>API (Brevo)</span>
            </label>
        </div>
    </fieldset>

    @if (($this->form['email_modo'] ?? 'smtp') === 'api')
        <fieldset class="erp-pcad__group erp-config-fiscais-form__email-group">
            <legend class="erp-pcad__group-title">API Brevo</legend>

            <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
                Use a <strong>API Key v3</strong> do painel Brevo (SMTP &amp; API → Chaves API). O remetente precisa estar verificado no Brevo.
            </p>

            <div class="erp-config-fiscais-form__email-grid erp-config-fiscais-form__email-grid--api">
                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-api-key">API Key</label>
                    <input
                        id="cfg-email-api-key"
                        type="text"
                        wire:model="form.email_api_key"
                        class="erp-pcad-form__input"
                        autocomplete="off"
                        data-erp-preserve-case
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        placeholder="xkeysib-..."
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-remetente">E-mail remetente</label>
                    <input
                        id="cfg-email-remetente"
                        type="text"
                        inputmode="email"
                        wire:model="form.email_user"
                        class="erp-pcad-form__input"
                        placeholder="contato@empresa.com.br"
                        autocomplete="off"
                        data-erp-preserve-case
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row erp-config-fiscais-form__email-assunto-row">
                    <label class="erp-pcad-form__label" for="cfg-email-assunto-api">Assunto padrão</label>
                    <input
                        id="cfg-email-assunto-api"
                        type="text"
                        wire:model="form.email_assunto"
                        class="erp-pcad-form__input"
                        autocomplete="off"
                        data-form-type="other"
                        placeholder="Documentos fiscais — Uni Sistemas"
                    >
                </div>
            </div>
        </fieldset>
    @else
        <fieldset class="erp-pcad__group erp-config-fiscais-form__email-group">
            <legend class="erp-pcad__group-title">Servidor SMTP</legend>

            <div class="erp-config-fiscais-form__email-grid">
                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-host">Servidor SMTP</label>
                    <input
                        id="cfg-email-host"
                        type="text"
                        wire:model="form.email_host"
                        class="erp-pcad-form__input"
                        placeholder="email-ssl.com.br"
                        autocomplete="off"
                        data-erp-preserve-case
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-porta">Porta</label>
                    <input
                        id="cfg-email-porta"
                        type="text"
                        wire:model="form.email_porta"
                        class="erp-pcad-form__input erp-config-fiscais-form__input--porta"
                        inputmode="numeric"
                        autocomplete="off"
                        data-form-type="other"
                        placeholder="465"
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-user">Usuário</label>
                    <input
                        id="cfg-email-user"
                        type="text"
                        inputmode="email"
                        wire:model="form.email_user"
                        class="erp-pcad-form__input"
                        placeholder="sac@empresa.com.br"
                        autocomplete="off"
                        data-erp-preserve-case
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
                    <label class="erp-pcad-form__label" for="cfg-email-pass">Senha</label>
                    <input
                        id="cfg-email-pass"
                        type="password"
                        wire:model="form.email_senha"
                        class="erp-pcad-form__input erp-pcad-form__input--password"
                        autocomplete="new-password"
                        data-erp-preserve-case
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        placeholder="Senha do e-mail SMTP"
                    >
                </div>

                <div class="erp-pcad-form__row erp-config-fiscais-form__field-row erp-config-fiscais-form__email-assunto-row">
                    <label class="erp-pcad-form__label" for="cfg-email-assunto">Assunto padrão</label>
                    <input
                        id="cfg-email-assunto"
                        type="text"
                        wire:model="form.email_assunto"
                        class="erp-pcad-form__input"
                        autocomplete="off"
                        data-form-type="other"
                        placeholder="Documentos fiscais — Uni Sistemas"
                    >
                </div>
            </div>

            <div class="erp-config-fiscais-form__email-checks">
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="form.email_ssl">
                    <span>SSL</span>
                </label>
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="form.email_tls">
                    <span>TLS</span>
                </label>
                <span class="erp-config-fiscais-form__email-checks-hint">Porta 465 → SSL; porta 587 → TLS.</span>
            </div>
        </fieldset>
    @endif

    <fieldset class="erp-pcad__group erp-config-fiscais-form__email-group erp-config-fiscais-form__email-test-group">
        <legend class="erp-pcad__group-title">Testar envio</legend>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            Envia um e-mail de teste com as configurações acima (não é necessário gravar antes).
        </p>

        <div class="erp-config-fiscais-form__email-test-row">
            <label class="erp-pcad-form__label" for="cfg-email-test-to">Enviar teste para</label>
            <input
                id="cfg-email-test-to"
                type="text"
                inputmode="email"
                wire:model="emailTestTo"
                class="erp-pcad-form__input erp-config-fiscais-form__email-test-input"
                placeholder="seu@email.com"
                autocomplete="off"
                data-erp-preserve-case
                data-lpignore="true"
                data-1p-ignore="true"
                data-form-type="other"
            >
            <button
                type="button"
                wire:click="testEmailSmtp"
                wire:loading.attr="disabled"
                wire:target="testEmailSmtp"
                class="erp-config-fiscais-form__verify-btn erp-config-fiscais-form__email-test-btn"
            >
                <span wire:loading.remove wire:target="testEmailSmtp">Enviar e-mail de teste</span>
                <span wire:loading wire:target="testEmailSmtp">Enviando…</span>
            </button>
        </div>
    </fieldset>
</div>
