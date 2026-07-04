@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::sistemaFields();
    $booleans = EmpresaParametros::sistemaBooleanFields();
    $backupStatus = EmpresaParametros::sistemaBackupStatusOptions();
    $updateUrl = $fields['param_update_download_url'] ?? null;
    unset($fields['param_update_download_url'], $fields['param_backup_ultimo_status']);
@endphp

<div class="erp-empresas-parametros__section-title">Atualização do sistema</div>

<p class="erp-empresas-parametros__hint">
    Link HTTPS direto do arquivo <strong>Unitec-ERP-Update.zip</strong> (Dropbox com <code>dl=1</code>, site ou nuvem).
    Usado em Ajuda → Atualizar Sistema. Se vazio, usa o valor do arquivo <code>.env</code> ou o site padrão.
</p>

@if ($updateUrl)
    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--url">
        <label class="erp-pcad-form__label" for="param-param_update_download_url">{{ $updateUrl['label'] }}</label>
        <input
            id="param-param_update_download_url"
            type="url"
            wire:model="data.param_update_download_url"
            class="erp-pcad-form__input erp-pcad-form__input--url-wide"
            placeholder="https://www.dropbox.com/.../Unitec-ERP-Update.zip?...&dl=1"
            spellcheck="false"
            autocomplete="off"
        >
    </div>
@endif

<div class="erp-empresas-parametros__section-title">Backup automático</div>

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<p class="erp-empresas-parametros__hint">
    Parâmetros gravados na base de dados desta empresa. O painel principal exibe o status do último backup conforme os campos abaixo.
</p>

<div class="erp-empresas-parametros__form-grid">
    @foreach ($fields as $field => $meta)
        <div class="erp-empresas-parametros__field">
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>

            @if ($field === 'param_backup_intervalo_horas')
                <input
                    id="param-{{ $field }}"
                    type="number"
                    min="1"
                    max="168"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                >
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

    <div class="erp-empresas-parametros__field">
        <label class="erp-pcad-form__label" for="param-param_backup_ultimo_status">Status do último backup</label>
        <select id="param-param_backup_ultimo_status" wire:model="data.param_backup_ultimo_status" class="erp-pcad-form__select erp-pcad-form__select--md">
            @foreach ($backupStatus as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
