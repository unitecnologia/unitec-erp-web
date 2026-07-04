<div class="erp-pcad-form erp-config-fiscais-form">
    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">NF-e</legend>

        <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
            <label class="erp-pcad-form__label" for="cfg-versao-nfe">Versão</label>
            <select id="cfg-versao-nfe" wire:model="form.versao_nfe" class="erp-pcad-form__select erp-pcad-form__input--grow">
                @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::versaoNfeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
            <label class="erp-pcad-form__label" for="cfg-tipo-emissao-nfe">Forma de emissão</label>
            <select id="cfg-tipo-emissao-nfe" wire:model="form.tipo_emissao" class="erp-pcad-form__select erp-pcad-form__input--grow">
                @foreach (\App\Support\Erp\Nfe\NfeFiscalConfig::tipoEmissaoNfeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group erp-config-fiscais-form__storage-group">
        <legend class="erp-pcad__group-title">Arquivos (automático no servidor)</legend>

        <p class="erp-config-fiscais-form__hint">
            Na versão web, XML e PDF são gravados no servidor (<code>storage/app/</code>), não em pastas
            <code>C:\</code> do Windows. O sistema cria e usa as pastas abaixo automaticamente.
        </p>

        @php
            $pathLabels = [
                'path_salvar_nfe' => 'Salvar XML (envio e resposta)',
                'path_schemas_nfe' => 'Schemas XSD',
                'path_enviada_nfe' => 'Enviadas / autorizadas',
                'path_can_nfe' => 'Cancelamento',
                'path_inuti_nfe' => 'Inutilização',
                'path_evento_nfe' => 'Eventos / CC-e',
                'path_pdf_nfe' => 'PDF DANFE',
            ];
        @endphp

        <dl class="erp-config-fiscais-form__paths">
            @foreach ($pathLabels as $key => $label)
                <div class="erp-config-fiscais-form__paths-row">
                    <dt>{{ $label }}</dt>
                    <dd><code>storage/app/{{ $this->form[$key] ?? '—' }}</code></dd>
                </div>
            @endforeach
        </dl>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">DANFE</legend>

        <div class="erp-pcad-form__row erp-config-fiscais-form__field-row">
            <label class="erp-pcad-form__label" for="cfg-logomarca">Logomarca</label>
            <input
                id="cfg-logomarca"
                type="text"
                wire:model="form.logomarca"
                class="erp-pcad-form__input erp-pcad-form__input--grow"
                placeholder="Caminho ou URL da logomarca (opcional)"
            >
        </div>
    </fieldset>
</div>
