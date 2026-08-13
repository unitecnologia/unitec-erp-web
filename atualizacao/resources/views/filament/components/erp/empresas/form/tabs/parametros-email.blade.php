@php
    $modo = $this->emailForm['email_modo'] ?? 'smtp';
@endphp

<div class="erp-empresas-email" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
    <div class="erp-empresas-parametros__section-title">Envio de e-mail</div>

    <p class="erp-empresas-parametros__hint">
        Usado no envio de NF-e, NFC-e, pacotes ao contador e relatórios por e-mail.
        Grave com <strong>F2 | Gravar</strong> ou pelo botão <strong>Gravar e-mail</strong> abaixo.
    </p>

    <div class="erp-empresas-email__mode">
        <label class="erp-pcad__check">
            <input type="radio" wire:model.live="emailForm.email_modo" value="smtp">
            <span>SMTP</span>
        </label>
        <label class="erp-pcad__check">
            <input type="radio" wire:model.live="emailForm.email_modo" value="api">
            <span>API (Brevo)</span>
        </label>
    </div>

    @if ($modo === 'api')
        <div class="erp-empresas-parametros__section-title">API Brevo</div>
        <p class="erp-empresas-parametros__hint">
            Use a <strong>API Key v3</strong> do painel Brevo (SMTP &amp; API → Chaves API). O remetente precisa estar verificado no Brevo.
        </p>

        <div class="erp-empresas-email__grid erp-empresas-email__grid--api">
            <label class="erp-empresas-parametros__field">
                <span>API Key</span>
                <input type="text" wire:model="emailForm.email_api_key" class="erp-pcad-form__input" autocomplete="off" data-erp-preserve-case placeholder="xkeysib-...">
            </label>
            <label class="erp-empresas-parametros__field">
                <span>E-mail remetente</span>
                <input type="text" inputmode="email" wire:model="emailForm.email_user" class="erp-pcad-form__input" autocomplete="off" data-erp-preserve-case placeholder="contato@empresa.com.br">
            </label>
            <label class="erp-empresas-parametros__field erp-empresas-email__field--full">
                <span>Assunto padrão</span>
                <input type="text" wire:model="emailForm.email_assunto" class="erp-pcad-form__input" autocomplete="off" placeholder="Documentos fiscais — Uni Sistemas">
            </label>
        </div>
    @else
        <div class="erp-empresas-parametros__section-title">Servidor SMTP</div>

        <div class="erp-empresas-email__grid">
            <label class="erp-empresas-parametros__field">
                <span>Servidor SMTP</span>
                <input type="text" wire:model="emailForm.email_host" class="erp-pcad-form__input" autocomplete="off" data-erp-preserve-case placeholder="email-ssl.com.br">
            </label>
            <label class="erp-empresas-parametros__field erp-empresas-email__field--porta">
                <span>Porta</span>
                <input type="text" wire:model="emailForm.email_porta" class="erp-pcad-form__input" inputmode="numeric" autocomplete="off" placeholder="465">
            </label>
            <label class="erp-empresas-parametros__field">
                <span>Usuário</span>
                <input type="text" inputmode="email" wire:model="emailForm.email_user" class="erp-pcad-form__input" autocomplete="off" data-erp-preserve-case placeholder="sac@empresa.com.br">
            </label>
            <label class="erp-empresas-parametros__field">
                <span>Senha</span>
                <input type="password" wire:model="emailForm.email_senha" class="erp-pcad-form__input erp-pcad-form__input--password" autocomplete="new-password" data-erp-preserve-case data-lpignore="true" data-1p-ignore="true" placeholder="Senha do e-mail SMTP">
            </label>
            <label class="erp-empresas-parametros__field erp-empresas-email__field--full">
                <span>Assunto padrão</span>
                <input type="text" wire:model="emailForm.email_assunto" class="erp-pcad-form__input" autocomplete="off" placeholder="Documentos fiscais — Uni Sistemas">
            </label>
        </div>

        <div class="erp-empresas-email__checks">
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model="emailForm.email_ssl">
                <span>SSL</span>
            </label>
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model="emailForm.email_tls">
                <span>TLS</span>
            </label>
            <span class="erp-empresas-parametros__hint">Porta 465 → SSL; porta 587 → TLS.</span>
        </div>
    @endif

    <div class="erp-empresas-parametros__section-title">Testar envio</div>
    <p class="erp-empresas-parametros__hint">
        Envia um e-mail de teste com as configurações acima (não é necessário gravar antes).
    </p>

    <div class="erp-empresas-email__test-row">
        <label class="erp-empresas-parametros__field">
            <span>Enviar teste para</span>
            <input type="text" inputmode="email" wire:model="emailTestTo" class="erp-pcad-form__input" autocomplete="off" data-erp-preserve-case placeholder="seu@email.com">
        </label>
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
            class="erp-pcad-form__btn"
            wire:click="saveEmpresaEmailConfig"
            wire:loading.attr="disabled"
            wire:target="saveEmpresaEmailConfig"
        >
            <span wire:loading.remove wire:target="saveEmpresaEmailConfig">Gravar e-mail</span>
            <span wire:loading wire:target="saveEmpresaEmailConfig">Gravando…</span>
        </button>
    </div>
</div>
