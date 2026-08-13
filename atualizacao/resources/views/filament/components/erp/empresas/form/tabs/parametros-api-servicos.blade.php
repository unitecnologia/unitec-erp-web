@php
    use App\Support\Erp\EmpresaParametros;

    $booleans = EmpresaParametros::apiServicosBooleanFields();
    $acessoRemotoFields = EmpresaParametros::acessoRemotoFields();
    $licencaFields = EmpresaParametros::licencaApiFields();
    $licencaBooleans = EmpresaParametros::licencaApiBooleanFields();

    $cfStatus = is_array($this->cloudflaredStatus ?? null) ? $this->cloudflaredStatus : [];
    $cfOnline = (bool) ($cfStatus['online'] ?? false);
    $cfCheckedAt = trim((string) ($cfStatus['checked_at'] ?? ''));
    $cfMessage = trim((string) ($cfStatus['message'] ?? ''));
    $cfUi = \App\Support\Erp\CloudflaredStatus::forUi();
@endphp

<div class="erp-empresas-api-servicos">
    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Busca Produto Auto</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            @foreach ($booleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="erp-empresas-api-servicos__rows">
            <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--url-timeout">
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_api_servicos_url">URL da API</label>
                    <input id="param-param_api_servicos_url" type="text" wire:model="data.param_api_servicos_url" class="erp-pcad-form__input erp-pcad-form__input--grow">
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field erp-empresas-api-servicos__field--timeout">
                    <label class="erp-pcad-form__label" for="param-param_api_servicos_timeout">Timeout</label>
                    <input id="param-param_api_servicos_timeout" type="number" min="1" max="300" wire:model="data.param_api_servicos_timeout" class="erp-pcad-form__input erp-pcad-form__input--xs">
                </div>
            </div>

            <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--2">
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_api_servicos_usuario">Usuário</label>
                    <input id="param-param_api_servicos_usuario" type="text" wire:model="data.param_api_servicos_usuario" class="erp-pcad-form__input erp-pcad-form__input--grow">
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_api_servicos_senha">Senha</label>
                    <input
                        id="param-param_api_servicos_senha"
                        type="password"
                        wire:model="data.param_api_servicos_senha"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-bwignore="true"
                        data-google-password-manager="ignore"
                    >
                </div>
            </div>

            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_api_servicos_token">Token / API Key</label>
                <input
                    id="param-param_api_servicos_token"
                    type="text"
                    wire:model="data.param_api_servicos_token"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    placeholder="Token Cosmos (Bluesoft)"
                >
            </div>
        </div>
    </section>

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Acesso remoto</h3>

        @php
            $acessoRemotoBooleans = EmpresaParametros::acessoRemotoBooleanFields();
            $acessoRemotoAtivo = (bool) ($this->data['param_acesso_remoto_habilitar'] ?? true);
        @endphp

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            @foreach ($acessoRemotoBooleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model.live="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        @if ($acessoRemotoAtivo)
            <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
                Crie o subdomínio/túnel na Cloudflare; aqui só informe as URLs públicas desta loja.
            </p>

            <div class="erp-empresas-api-servicos__rows">
                @foreach ($acessoRemotoFields as $field => $meta)
                    <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                        <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
                        <input
                            id="param-{{ $field }}"
                            type="url"
                            wire:model="data.{{ $field }}"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                            data-erp-preserve-case
                            autocomplete="off"
                            placeholder="{{ $meta['default'] }}"
                        >
                    </div>
                @endforeach
            </div>

            <div class="erp-empresas-api-servicos__status-row">
                <span
                    @class([
                        'erp-empresas-api-servicos__badge',
                        'erp-empresas-api-servicos__badge--online' => $cfOnline,
                        'erp-empresas-api-servicos__badge--offline' => ! $cfOnline,
                    ])
                    title="{{ $cfOnline ? 'Túnel Cloudflare online' : 'Túnel Cloudflare offline' }}"
                >
                    <span class="erp-empresas-api-servicos__badge-dot" aria-hidden="true"></span>
                    {{ $cfOnline ? 'Online' : 'Offline' }}
                </span>

                <span class="erp-empresas-api-servicos__status-detail">
                    {{ $cfUi['detail'] }}
                    @if (! $cfOnline && $cfCheckedAt !== '')
                        · Verificado: {{ \App\Support\Erp\ErpTimezone::toLocal($cfCheckedAt)->format('d/m/Y H:i:s') }}
                    @endif
                </span>

                <button
                    type="button"
                    class="erp-pcad-btn erp-pcad-btn--ghost erp-empresas-api-servicos__refresh"
                    wire:click="refreshCloudflaredStatus"
                    wire:loading.attr="disabled"
                >
                    Atualizar
                </button>
            </div>

            @if ($cfMessage !== '')
                <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">{{ $cfMessage }}</p>
            @endif
        @else
            <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
                Acesso remoto desativado — túnel Cloudflare e URLs públicas não são usados nesta empresa.
            </p>
        @endif
    </section>

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Licença do Sistema</h3>

        <div class="erp-empresas-api-servicos__licenca">
            <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
                @foreach ($licencaBooleans as $field => $meta)
                    <label class="erp-pcad__check">
                        <input type="checkbox" wire:model="data.{{ $field }}">
                        <span>{{ $meta['label'] }}</span>
                    </label>
                @endforeach
            </div>

            @foreach ($licencaFields as $field => $meta)
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field erp-empresas-api-servicos__field--timeout">
                    <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
                    <input
                        id="param-{{ $field }}"
                        type="number"
                        min="2"
                        max="30"
                        wire:model="data.{{ $field }}"
                        class="erp-pcad-form__input erp-pcad-form__input--xs"
                    >
                </div>
            @endforeach
        </div>
    </section>
</div>
