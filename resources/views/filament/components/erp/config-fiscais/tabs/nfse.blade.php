@php
    use App\Support\Erp\Nfse\NfseRegimeTributario;
    use App\Support\Erp\Nfse\NfseSefinAmbiente;

    $regimesEspeciais = NfseRegimeTributario::regimesEspeciais();
    $regimesApuracao = NfseRegimeTributario::regimesApuracaoSimples();
    $ambientesNfse = NfseSefinAmbiente::opcoes();
    $provedorNfse = (string) ($this->form['nfse_provedor'] ?? 'nacional');
@endphp

<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfe">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">NFS-e</legend>

        <div class="erp-config-fiscais-form__nfe-fields">
            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-nfse-provedor">Provedor NFS-e</label>
                <select id="cfg-nfse-provedor" wire:model.live="form.nfse_provedor" class="erp-pcad-form__select">
                    <option value="nacional">Nacional</option>
                    <option value="ipm">IPM</option>
                </select>
            </div>

            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-nfse-ambiente">Ambiente</label>
                <select id="cfg-nfse-ambiente" wire:model="form.nfse_ambiente" class="erp-pcad-form__select">
                    <option value="">Selecione</option>
                    @foreach ($ambientesNfse as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-nfse-reg-esp">Regime especial de tributação</label>
                <select id="cfg-nfse-reg-esp" wire:model="form.nfse_reg_esp_trib" class="erp-pcad-form__select">
                    <option value="">Selecione</option>
                    @foreach ($regimesEspeciais as $value => $label)
                        <option value="{{ $value }}">{{ $value }} — {{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-nfse-reg-ap">Regime de apuração do Simples</label>
                <select id="cfg-nfse-reg-ap" wire:model="form.nfse_reg_ap_trib_sn" class="erp-pcad-form__select">
                    <option value="">Não informado</option>
                    @foreach ($regimesApuracao as $value => $label)
                        <option value="{{ $value }}">{{ $value }} — {{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($provedorNfse === 'ipm')
                <div class="erp-config-fiscais-form__nfe-field">
                    <label class="erp-pcad-form__label" for="cfg-nfse-ws-usuario">Usuário WebService (CNPJ da empresa)</label>
                    <input
                        id="cfg-nfse-ws-usuario"
                        type="text"
                        value="{{ $this->form['nfse_ws_usuario'] ?? '' }}"
                        class="erp-pcad-form__input"
                        placeholder="CNPJ da empresa inválido"
                        readonly
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>

                <div class="erp-config-fiscais-form__nfe-field">
                    <label class="erp-pcad-form__label" for="cfg-nfse-ws-senha">Senha WebService</label>
                    <input
                        id="cfg-nfse-ws-senha"
                        type="password"
                        wire:model="form.nfse_ws_senha"
                        class="erp-pcad-form__input"
                        maxlength="120"
                        autocomplete="new-password"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-bwignore="true"
                        data-google-password-manager="ignore"
                    >
                </div>

                <div class="erp-config-fiscais-form__nfe-field erp-config-fiscais-form__nfe-field--span">
                    <label class="erp-pcad-form__label" for="cfg-nfse-url-producao">URL Produção</label>
                    <input
                        id="cfg-nfse-url-producao"
                        type="text"
                        wire:model="form.nfse_url_producao"
                        class="erp-pcad-form__input"
                        maxlength="255"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>

                <div class="erp-config-fiscais-form__nfe-field erp-config-fiscais-form__nfe-field--span">
                    <label class="erp-pcad-form__label" for="cfg-nfse-url-homologacao">URL Homologação</label>
                    <input
                        id="cfg-nfse-url-homologacao"
                        type="text"
                        wire:model="form.nfse_url_homologacao"
                        class="erp-pcad-form__input"
                        maxlength="255"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>

                <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact erp-config-fiscais-form__nfe-field--span">
                    No IPM, o ambiente Produção restrita envia em modo teste (EnvioTeste=1): a prefeitura valida o RPS e nenhuma NFS-e é emitida. Sem URL de homologação, o teste usa a URL de produção.
                </p>
            @endif
        </div>

        @if ($provedorNfse === 'ipm')
            <div class="erp-config-fiscais-form__nfse-num-row">
                <label class="erp-pcad-form__label" for="cfg-nfse-serie-rps">Série RPS</label>
                <input
                    id="cfg-nfse-serie-rps"
                    type="text"
                    wire:model="form.nfse_serie_rps"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                    maxlength="5"
                    autocomplete="off"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfse-proximo-rps">Próximo Nº RPS</label>
                <input
                    id="cfg-nfse-proximo-rps"
                    type="number"
                    wire:model="form.nfse_proximo_rps"
                    class="erp-pcad-form__input erp-pcad-form__input--dps"
                    min="1"
                    step="1"
                    autocomplete="off"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfse-tipo-rps">Tipo RPS</label>
                <input
                    id="cfg-nfse-tipo-rps"
                    type="text"
                    inputmode="numeric"
                    wire:model="form.nfse_tipo_rps"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                    maxlength="1"
                    autocomplete="off"
                >
            </div>
        @else
            <div class="erp-config-fiscais-form__nfse-num-row">
                <label class="erp-pcad-form__label" for="cfg-nfse-serie-dps">Série DPS</label>
                <input
                    id="cfg-nfse-serie-dps"
                    type="text"
                    inputmode="numeric"
                    wire:model="form.nfse_serie_dps"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                    maxlength="5"
                    autocomplete="off"
                >

                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfse-proximo-dps">Próximo Nº DPS</label>
                <input
                    id="cfg-nfse-proximo-dps"
                    type="number"
                    wire:model="form.nfse_proximo_dps"
                    class="erp-pcad-form__input erp-pcad-form__input--dps"
                    min="1"
                    step="1"
                    autocomplete="off"
                >
            </div>

            <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
                Continuidade de outro sistema: última DPS 150, próximo número 151. DPS já gravadas não mudam.
            </p>
        @endif

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            O regime especial é obrigatório para emitir NFS-e. A apuração do Simples é opcional.
        </p>
    </fieldset>
</div>
