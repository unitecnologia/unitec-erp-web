@php
    use App\Models\Contador;
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::portalContadorManualFields();
    $booleans = EmpresaParametros::portalContadorBooleanFields();
    $ambientes = EmpresaParametros::portalContadorAmbienteOptions();
    $contadores = Contador::query()->orderBy('nome')->pluck('nome', 'id');
    $conectado = filled($this->data['param_portal_contador_token'] ?? null);

    $habilitar = $booleans['param_portal_contador_habilitar'] ?? null;
    unset($booleans['param_portal_contador_habilitar']);
@endphp

<div class="erp-empresas-api-servicos erp-empresas-portal-contador">
    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Portal do Contador</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            @if ($habilitar)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.param_portal_contador_habilitar">
                    <span>{{ $habilitar['label'] }}</span>
                </label>
            @endif
        </div>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Envio automático de documentos fiscais para o portal na nuvem.
            <strong>Conectar ao Portal</strong> envia CNPJ/razão social; o contador autoriza e o token é preenchido.
        </p>

        <div class="erp-portal-contador-vinculo-panel">
            <div class="erp-portal-contador-vinculo-panel__status">
                <span @class([
                    'erp-portal-contador-vinculo-panel__badge',
                    'erp-portal-contador-vinculo-panel__badge--connected' => $conectado,
                    'erp-portal-contador-vinculo-panel__badge--disconnected' => ! $conectado,
                ])>{{ $conectado ? 'Conectado' : 'Não conectado' }}</span>
                <p>{{ $this->portalContadorVinculoResumo() }}</p>
                @if (filled($this->data['param_portal_contador_contador_nome_portal'] ?? null))
                    <p class="erp-portal-contador-vinculo-panel__meta">
                        Contador no portal: <strong>{{ $this->data['param_portal_contador_contador_nome_portal'] }}</strong>
                    </p>
                @endif
            </div>

            <div class="erp-portal-contador-vinculo-panel__actions">
                <button
                    type="button"
                    class="erp-pcad-form__btn erp-portal-contador-vinculo-panel__btn-primary"
                    wire:click="startPortalContadorVinculo"
                    wire:loading.attr="disabled"
                    wire:target="startPortalContadorVinculo"
                >
                    <span wire:loading.remove wire:target="startPortalContadorVinculo">Conectar ao Portal</span>
                    <span wire:loading wire:target="startPortalContadorVinculo">Enviando solicitação…</span>
                </button>

                @if ($conectado)
                    <button
                        type="button"
                        class="erp-pcad-form__btn"
                        wire:click="desvincularPortalContador"
                        wire:confirm="Remover o vínculo com o portal nesta empresa?"
                    >Desvincular</button>
                @endif
            </div>
        </div>
    </section>

    <details class="erp-portal-contador-advanced erp-empresas-api-servicos__panel">
        <summary class="erp-empresas-api-servicos__panel-title erp-portal-contador-advanced__summary">
            Configuração manual (avançado)
        </summary>

        <div class="erp-empresas-api-servicos__rows">
            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_portal_contador_url">URL da API</label>
                <input
                    id="param-param_portal_contador_url"
                    type="text"
                    wire:model="data.param_portal_contador_url"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    placeholder="https://unitecnologiasc.com.br/api/portal/documentos"
                    spellcheck="false"
                    autocomplete="off"
                >
            </div>

            <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--2">
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_portal_contador_empresa_id">ID empresa nuvem</label>
                    <input
                        id="param-param_portal_contador_empresa_id"
                        type="text"
                        wire:model="data.param_portal_contador_empresa_id"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                    >
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_portal_contador_token">Token / API Key</label>
                    <input
                        id="param-param_portal_contador_token"
                        type="password"
                        wire:model="data.param_portal_contador_token"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-bwignore="true"
                        data-google-password-manager="ignore"
                    >
                </div>
            </div>

            @php
                $manualTimeout = $fields['param_portal_contador_timeout'] ?? null;
                $manualAmbiente = $fields['param_portal_contador_ambiente'] ?? null;
                $manualRest = collect($fields)->except([
                    'param_portal_contador_timeout',
                    'param_portal_contador_ambiente',
                ]);
            @endphp

            @if ($manualTimeout || $manualAmbiente)
                <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--2">
                    @if ($manualTimeout)
                        <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                            <label class="erp-pcad-form__label" for="param-param_portal_contador_timeout">{{ $manualTimeout['label'] }}</label>
                            <input
                                id="param-param_portal_contador_timeout"
                                type="number"
                                min="1"
                                max="300"
                                wire:model="data.param_portal_contador_timeout"
                                class="erp-pcad-form__input erp-pcad-form__input--xs"
                            >
                        </div>
                    @endif
                    @if ($manualAmbiente)
                        <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                            <label class="erp-pcad-form__label" for="param-param_portal_contador_ambiente">{{ $manualAmbiente['label'] }}</label>
                            <select id="param-param_portal_contador_ambiente" wire:model="data.param_portal_contador_ambiente" class="erp-pcad-form__select erp-pcad-form__select--md">
                                @foreach ($ambientes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            @endif

            @foreach ($manualRest as $field => $meta)
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>

                    @if ($field === 'param_portal_contador_contador_id')
                        <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                            <option value="">— Nenhum —</option>
                            @foreach ($contadores as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="param-{{ $field }}"
                            type="text"
                            wire:model="data.{{ $field }}"
                            class="erp-pcad-form__input erp-pcad-form__input--grow"
                        >
                    @endif
                </div>
            @endforeach
        </div>
    </details>

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">O que enviar</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks erp-empresas-portal-contador__checks">
            @foreach ($booleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="erp-empresas-api-servicos__provision-actions erp-empresas-portal-contador__actions">
            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="atualizarPortalContador"
                wire:loading.attr="disabled"
                wire:target="atualizarPortalContador"
            >
                <span wire:loading.remove wire:target="atualizarPortalContador">Atualizar</span>
                <span wire:loading wire:target="atualizarPortalContador">Atualizando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="testPortalContadorConnection"
                wire:loading.attr="disabled"
                wire:target="testPortalContadorConnection"
            >
                <span wire:loading.remove wire:target="testPortalContadorConnection">Testar conexão</span>
                <span wire:loading wire:target="testPortalContadorConnection">Testando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="openPortalContadorLogModal"
                wire:loading.attr="disabled"
                wire:target="openPortalContadorLogModal"
            >
                <span wire:loading.remove wire:target="openPortalContadorLogModal">Log</span>
                <span wire:loading wire:target="openPortalContadorLogModal">Abrindo…</span>
            </button>
        </div>
    </section>
</div>
