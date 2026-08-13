@php

    $booleans = \App\Support\Erp\EmpresaParametros::mercadoLivreBooleanFields();

    $conectado = filled($this->data['param_meli_access_token'] ?? null);

    $habilitar = $booleans['param_meli_habilitar'] ?? null;

    $empresaContext = $this->record ?? null;

    $hubUrl = app(\App\Support\MercadoLivre\MeliHubService::class)->hubBaseUrl($empresaContext);

    $aguardandoHub = filled($this->meliHubPairId);

    $webhookUrl = $hubUrl.'/api/webhooks/mercadolivre';

@endphp



<div

    class="erp-meli-tab"

    @if ($aguardandoHub) wire:poll.3s="pollMercadoLivreHubPair" @endif

>

    <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">

        @if ($habilitar)

            <label class="erp-pcad__check">

                <input type="checkbox" wire:model="data.param_meli_habilitar">

                <span>{{ $habilitar['label'] }}</span>

            </label>

        @endif

    </div>



    <p class="erp-empresas-parametros__hint">

        Integração via servidor Unitec. O cliente <strong>não precisa</strong> de domínio nem de app no Mercado Livre Developers.

        Basta autorizar a conta do vendedor.

    </p>



    <div class="erp-empresas-parametros__section-title">Conta do vendedor</div>



    <div class="erp-portal-contador-vinculo-panel">

        <div class="erp-portal-contador-vinculo-panel__status">

            <span @class([

                'erp-portal-contador-vinculo-panel__badge',

                'erp-portal-contador-vinculo-panel__badge--connected' => $conectado,

                'erp-portal-contador-vinculo-panel__badge--disconnected' => ! $conectado,

            ])>{{ $conectado ? 'Conectado' : ($aguardandoHub ? 'Aguardando autorização…' : 'Não conectado') }}</span>

            <p>{{ $this->mercadoLivreVinculoResumo() }}</p>

            @if (filled($this->data['param_meli_user_id'] ?? null))

                <p class="erp-portal-contador-vinculo-panel__meta">

                    ID ML: <strong>{{ $this->data['param_meli_user_id'] }}</strong>

                </p>

            @endif

        </div>



        <div class="erp-portal-contador-vinculo-panel__actions">

            <button

                type="button"

                class="erp-pcad-form__btn erp-portal-contador-vinculo-panel__btn-primary"

                wire:click="startMercadoLivreVinculo"

                wire:loading.attr="disabled"

                wire:target="startMercadoLivreVinculo"

                @disabled($aguardandoHub)

            >

                <span wire:loading.remove wire:target="startMercadoLivreVinculo">Conectar conta Mercado Livre</span>

                <span wire:loading wire:target="startMercadoLivreVinculo">Abrindo autorização…</span>

            </button>



            @if ($conectado)

                <button

                    type="button"

                    class="erp-pcad-form__btn"

                    wire:click="testMercadoLivreConnection"

                    wire:loading.attr="disabled"

                    wire:target="testMercadoLivreConnection"

                >

                    <span wire:loading.remove wire:target="testMercadoLivreConnection">Testar conexão</span>

                    <span wire:loading wire:target="testMercadoLivreConnection">Testando…</span>

                </button>



                <button

                    type="button"

                    class="erp-pcad-form__btn"

                    wire:click="desvincularMercadoLivre"

                    wire:confirm="Remover o vínculo com o Mercado Livre nesta empresa?"

                >Desvincular</button>

            @endif

        </div>

    </div>



    <details class="erp-portal-contador-advanced" open>

        <summary>Configuração avançada (Unitec)</summary>



        <p class="erp-empresas-parametros__hint">

            Estes campos são gravados no banco com <strong>Gravar (F5)</strong>.

            No servidor Unitec deixe <strong>Este servidor é o hub</strong> marcado e o APP URL com HTTPS público.

        </p>



        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">

            <label class="erp-pcad__check">

                <input type="checkbox" wire:model="data.param_meli_is_hub">

                <span>Este servidor é o hub</span>

            </label>

        </div>



        <div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--wide">

            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-meli-app-url">APP URL</label>

                <input

                    id="param-meli-app-url"

                    type="text"

                    wire:model="data.param_meli_app_url"

                    class="erp-pcad-form__input erp-pcad-form__input--grow"

                    placeholder="https://unitecnologiasc.com.br"

                    autocomplete="off"

                    spellcheck="false"

                >

            </div>



            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-meli-hub-url">Hub URL</label>

                <input

                    id="param-meli-hub-url"

                    type="text"

                    wire:model="data.param_meli_hub_url"

                    class="erp-pcad-form__input erp-pcad-form__input--grow"

                    placeholder="https://unitecnologiasc.com.br"

                    autocomplete="off"

                    spellcheck="false"

                >

            </div>



            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-param_meli_client_id">Client ID</label>

                <input

                    id="param-param_meli_client_id"

                    type="text"

                    wire:model="data.param_meli_client_id"

                    class="erp-pcad-form__input erp-pcad-form__input--grow"

                    autocomplete="off"

                    spellcheck="false"

                >

            </div>



            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-param_meli_client_secret">Client Secret</label>

                <div class="erp-empresas-parametros__password" x-data="{ show: false }">

                    <input

                        id="param-param_meli_client_secret"

                        :type="show ? 'text' : 'password'"

                        wire:model="data.param_meli_client_secret"

                        class="erp-pcad-form__input erp-pcad-form__input--grow"

                        autocomplete="new-password"

                        spellcheck="false"

                    >

                    <button

                        type="button"

                        class="erp-empresas-parametros__password-toggle"

                        @click="show = ! show"

                        :title="show ? 'Ocultar secret' : 'Mostrar secret'"

                        :aria-label="show ? 'Ocultar secret' : 'Mostrar secret'"

                        :class="{ 'is-visible': show }"

                    >

                        <svg class="erp-empresas-parametros__password-icon erp-empresas-parametros__password-icon--show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>

                            <circle cx="12" cy="12" r="3"/>

                        </svg>

                        <svg class="erp-empresas-parametros__password-icon erp-empresas-parametros__password-icon--hide" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>

                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>

                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>

                            <line x1="1" y1="1" x2="23" y2="23"/>

                        </svg>

                    </button>

                </div>

            </div>



            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-param_meli_redirect_uri">URI de redirect</label>

                <input

                    id="param-param_meli_redirect_uri"

                    type="text"

                    wire:model="data.param_meli_redirect_uri"

                    class="erp-pcad-form__input erp-pcad-form__input--grow"

                    autocomplete="off"

                    spellcheck="false"

                >

            </div>



            <div class="erp-empresas-parametros__field">

                <label class="erp-pcad-form__label" for="param-meli-webhook-url">Webhook</label>

                <input

                    id="param-meli-webhook-url"

                    type="text"

                    class="erp-pcad-form__input erp-pcad-form__input--grow"

                    value="{{ $webhookUrl }}"

                    readonly

                    onclick="this.select()"

                >

            </div>

        </div>

    </details>

</div>

