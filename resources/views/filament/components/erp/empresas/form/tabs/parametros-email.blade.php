@php
    $modo = $this->emailForm['email_modo'] ?? 'smtp';
@endphp

<div class="erp-empresas-api-servicos erp-empresas-email" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Envio de e-mail</h3>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Usado em NF-e, NFC-e, pacotes ao contador e relatórios.
            Grave com <strong>F2</strong> ou <strong>Gravar e-mail</strong>.
        </p>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            <label class="erp-pcad__check">
                <input type="radio" wire:model.live="emailForm.email_modo" value="smtp">
                <span>SMTP</span>
            </label>
            <label class="erp-pcad__check">
                <input type="radio" wire:model.live="emailForm.email_modo" value="api">
                <span>API (Brevo)</span>
            </label>
        </div>
    </section>

    @if ($modo === 'api')
        <section class="erp-empresas-api-servicos__panel">
            <h3 class="erp-empresas-api-servicos__panel-title">API Brevo</h3>

            <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
                Use a <strong>API Key v3</strong> do Brevo (SMTP &amp; API → Chaves API). Remetente precisa estar verificado.
            </p>

            <div class="erp-empresas-api-servicos__rows">
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="email-api-key">API Key</label>
                    <input
                        id="email-api-key"
                        type="text"
                        wire:model="emailForm.email_api_key"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        data-erp-preserve-case
                        placeholder="xkeysib-..."
                    >
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="email-api-user">E-mail remetente</label>
                    <input
                        id="email-api-user"
                        type="text"
                        inputmode="email"
                        wire:model="emailForm.email_user"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        data-erp-preserve-case
                        placeholder="contato@empresa.com.br"
                    >
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="email-api-assunto">Assunto padrão</label>
                    <input
                        id="email-api-assunto"
                        type="text"
                        wire:model="emailForm.email_assunto"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        placeholder="Documentos fiscais — Uni Sistemas"
                    >
                </div>
            </div>
        </section>
    @else
        <section class="erp-empresas-api-servicos__panel">
            <h3 class="erp-empresas-api-servicos__panel-title">Servidor SMTP</h3>

            <div class="erp-empresas-api-servicos__rows">
                <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--url-timeout">
                    <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                        <label class="erp-pcad-form__label" for="email-smtp-host">Servidor SMTP</label>
                        <input
                            id="email-smtp-host"
                            type="text"
                            wire:model="emailForm.email_host"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            autocomplete="off"
                            data-erp-preserve-case
                            placeholder="email-ssl.com.br"
                        >
                    </div>
                    <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field erp-empresas-api-servicos__field--timeout">
                        <label class="erp-pcad-form__label" for="email-smtp-porta">Porta</label>
                        <input
                            id="email-smtp-porta"
                            type="text"
                            wire:model="emailForm.email_porta"
                            class="erp-pcad-form__input erp-pcad-form__input--xs"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="465"
                        >
                    </div>
                </div>

                <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--2">
                    <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                        <label class="erp-pcad-form__label" for="email-smtp-user">Usuário</label>
                        <input
                            id="email-smtp-user"
                            type="text"
                            inputmode="email"
                            wire:model="emailForm.email_user"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            autocomplete="off"
                            data-erp-preserve-case
                            placeholder="sac@empresa.com.br"
                        >
                    </div>
                    <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                        <label class="erp-pcad-form__label" for="email-smtp-senha">Senha</label>
                        <input
                            id="email-smtp-senha"
                            type="password"
                            wire:model="emailForm.email_senha"
                            class="erp-pcad-form__input erp-pcad-form__input--grow erp-pcad-form__input--password"
                            autocomplete="new-password"
                            data-erp-preserve-case
                            data-lpignore="true"
                            data-1p-ignore="true"
                            placeholder="Senha SMTP"
                        >
                    </div>
                </div>

                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="email-smtp-assunto">Assunto padrão</label>
                    <input
                        id="email-smtp-assunto"
                        type="text"
                        wire:model="emailForm.email_assunto"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        placeholder="Documentos fiscais — Uni Sistemas"
                    >
                </div>
            </div>

            <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks erp-empresas-email__checks">
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="emailForm.email_ssl">
                    <span>SSL</span>
                </label>
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="emailForm.email_tls">
                    <span>TLS</span>
                </label>
                <span class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">Porta 465 → SSL; 587 → TLS.</span>
            </div>
        </section>
    @endif

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Testar envio</h3>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Envia um e-mail de teste com as configurações acima (não precisa gravar antes).
        </p>

        <div class="erp-empresas-api-servicos__rows">
            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="email-test-to">Enviar teste para</label>
                <input
                    id="email-test-to"
                    type="text"
                    inputmode="email"
                    wire:model="emailTestTo"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    autocomplete="off"
                    data-erp-preserve-case
                    placeholder="seu@email.com"
                >
            </div>
        </div>

        <div class="erp-empresas-api-servicos__provision-actions erp-empresas-email__actions">
            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="testEmpresaEmail"
                wire:loading.attr="disabled"
                wire:target="testEmpresaEmail"
            >
                <span wire:loading.remove wire:target="testEmpresaEmail">Enviar e-mail de teste</span>
                <span wire:loading wire:target="testEmpresaEmail">Enviando…</span>
            </button>
            <button
                type="button"
                class="erp-pcad-form__btn erp-portal-contador-vinculo-panel__btn-primary"
                wire:click="saveEmpresaEmailConfig"
                wire:loading.attr="disabled"
                wire:target="saveEmpresaEmailConfig"
            >
                <span wire:loading.remove wire:target="saveEmpresaEmailConfig">Gravar e-mail</span>
                <span wire:loading wire:target="saveEmpresaEmailConfig">Gravando…</span>
            </button>
        </div>
    </section>
</div>
