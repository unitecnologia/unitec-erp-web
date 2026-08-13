<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfe">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">NF-e</legend>

        <div class="erp-config-fiscais-form__nfe-fields">
            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-versao-nfe">Versão</label>
                <select id="cfg-versao-nfe" wire:model="form.versao_nfe" class="erp-pcad-form__select">
                    @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::versaoNfeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="erp-config-fiscais-form__nfe-field">
                <label class="erp-pcad-form__label" for="cfg-tipo-emissao-nfe">Forma de emissão</label>
                <select id="cfg-tipo-emissao-nfe" wire:model="form.tipo_emissao" class="erp-pcad-form__select">
                    @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::tipoEmissaoNfeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">Numeração</legend>

        <div class="erp-config-fiscais-form__nfce-inline-row">
            <label class="erp-pcad-form__label" for="cfg-nfe-serie">Série</label>
            <input
                id="cfg-nfe-serie"
                type="number"
                wire:model.live="form.serie_nfe"
                class="erp-pcad-form__input erp-pcad-form__input--xs"
                min="1"
                max="999"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="cfg-nfe-numero">Próx. nº</label>
            <input
                id="cfg-nfe-numero"
                type="number"
                wire:model="form.numero_nfe"
                class="erp-pcad-form__input erp-pcad-form__input--xs"
                min="1"
            >

            <label class="erp-pcad-form__label erp-pcad-form__label--inline">Últ. NF-e</label>
            <output class="erp-config-fiscais-form__ult-nfce" aria-live="polite">{{ $this->ultimaNfeNumeroLabel() }}</output>
        </div>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            A numeração da NF-e é independente da NFC-e.
        </p>
    </fieldset>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">Arquivos no servidor</legend>

        <p class="erp-config-fiscais-form__hint erp-config-fiscais-form__hint--compact">
            XML e PDF em <code>storage/app/</code> — pastas criadas automaticamente.
        </p>

        @php
            $pathLabels = [
                'path_salvar_nfe' => 'Salvar XML',
                'path_schemas_nfe' => 'Schemas XSD',
                'path_enviada_nfe' => 'Enviadas',
                'path_can_nfe' => 'Cancelamento',
                'path_inuti_nfe' => 'Inutilização',
                'path_evento_nfe' => 'Eventos / CC-e',
                'path_pdf_nfe' => 'PDF DANFE',
            ];
        @endphp

        <dl class="erp-config-fiscais-form__paths erp-config-fiscais-form__paths--compact">
            @foreach ($pathLabels as $key => $label)
                <div class="erp-config-fiscais-form__paths-row">
                    <dt>{{ $label }}</dt>
                    <dd><code>storage/app/{{ $this->form[$key] ?? '—' }}</code></dd>
                </div>
            @endforeach
        </dl>
    </fieldset>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfe-group">
        <legend class="erp-pcad__group-title">DANFE</legend>

        <div class="erp-config-fiscais-form__nfe-field erp-config-fiscais-form__nfe-field--full">
            <label class="erp-pcad-form__label" for="cfg-logomarca">Logomarca</label>
            <input
                id="cfg-logomarca"
                type="text"
                wire:model="form.logomarca"
                class="erp-pcad-form__input"
                placeholder="Caminho ou URL da logomarca (opcional)"
            >
        </div>
    </fieldset>
</div>
