@php
    use App\Support\Erp\Nfse\NfseRegimeTributario;
    use App\Support\Erp\Nfse\NfseSefinAmbiente;

    $regimesEspeciais = NfseRegimeTributario::regimesEspeciais();
    $regimesApuracao = NfseRegimeTributario::regimesApuracaoSimples();
    $ambientesNfse = NfseSefinAmbiente::opcoes();
@endphp

<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfe">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">NFS-e</legend>

        <div class="erp-config-fiscais-form__nfe-fields">
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
        </div>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            O regime especial é obrigatório para emitir NFS-e. A apuração do Simples é opcional.
        </p>
    </fieldset>
</div>
