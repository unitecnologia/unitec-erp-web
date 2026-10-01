@php
    use App\Support\Erp\EmpresaParametros;

    $booleans = EmpresaParametros::pixBooleanFields();
    $provedores = EmpresaParametros::pixProvedorOptions();
    $provedor = (string) ($this->data['param_pix_provedor'] ?? 'mercadopago');
    $isAilos = $provedor === 'ailos';
    $isMp = $provedor === 'mercadopago';
    $webhookPadrao = EmpresaParametros::pixAilosWebhookUrl();
@endphp

<div class="erp-empresas-pix">
    <div class="erp-empresas-pix__panel">
        <div class="erp-empresas-pix__panel-title">API PIX</div>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-pix__checks">
            @foreach ($booleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="erp-empresas-pix__grid">
            <div class="erp-empresas-pix__field">
                <label class="erp-pcad-form__label" for="param-param_pix_provedor">Provedor</label>
                <select
                    id="param-param_pix_provedor"
                    wire:model.live="data.param_pix_provedor"
                    class="erp-pcad-form__select"
                >
                    @foreach ($provedores as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="erp-empresas-pix__field">
                <label class="erp-pcad-form__label" for="param-param_pix_ambiente">Ambiente</label>
                <select
                    id="param-param_pix_ambiente"
                    wire:model.live="data.param_pix_ambiente"
                    class="erp-pcad-form__select"
                >
                    <option value="homologacao">Homologação</option>
                    <option value="producao">Produção</option>
                </select>
            </div>
        </div>

        @if ($isMp)
            <p class="erp-empresas-pix__hint">Token do Mercado Pago desta empresa.</p>
            <div class="erp-empresas-pix__grid erp-empresas-pix__grid--stack">
                <div class="erp-empresas-pix__field erp-empresas-pix__field--full">
                    <label class="erp-pcad-form__label" for="param-param_pix_mp_access_token">Access Token</label>
                    <input
                        id="param-param_pix_mp_access_token"
                        type="password"
                        autocomplete="new-password"
                        wire:model="data.param_pix_mp_access_token"
                        class="erp-pcad-form__input"
                    >
                </div>
            </div>
        @endif

        @if ($isAilos)
            <p class="erp-empresas-pix__hint">
                Credenciais desta empresa (cada cliente tem as suas). O certificado mTLS é o A1 da NF-e
                já cadastrado em Configurações Fiscais — não cole PFX nesta tela.
            </p>

            <div class="erp-empresas-pix__grid">
                <div class="erp-empresas-pix__field">
                    <label class="erp-pcad-form__label" for="param-param_pix_client_id">Client ID</label>
                    <input
                        id="param-param_pix_client_id"
                        type="text"
                        wire:model="data.param_pix_client_id"
                        class="erp-pcad-form__input"
                        autocomplete="off"
                    >
                </div>

                <div class="erp-empresas-pix__field">
                    <label class="erp-pcad-form__label" for="param-param_pix_client_secret">Client Secret</label>
                    <input
                        id="param-param_pix_client_secret"
                        type="password"
                        autocomplete="new-password"
                        wire:model="data.param_pix_client_secret"
                        class="erp-pcad-form__input"
                    >
                </div>

                <div class="erp-empresas-pix__field erp-empresas-pix__field--full">
                    <label class="erp-pcad-form__label" for="param-param_pix_chave">Chave PIX</label>
                    <input
                        id="param-param_pix_chave"
                        type="text"
                        wire:model="data.param_pix_chave"
                        class="erp-pcad-form__input"
                        autocomplete="off"
                        placeholder="Chave aleatória, e-mail, CPF/CNPJ ou telefone"
                    >
                </div>

                <div class="erp-empresas-pix__field erp-empresas-pix__field--full">
                    <label class="erp-pcad-form__label" for="param-param_pix_webhook_url">Webhook</label>
                    <input
                        id="param-param_pix_webhook_url"
                        type="text"
                        wire:model="data.param_pix_webhook_url"
                        class="erp-pcad-form__input"
                        placeholder="{{ $webhookPadrao }}"
                    >
                </div>
            </div>
        @endif
    </div>
</div>
