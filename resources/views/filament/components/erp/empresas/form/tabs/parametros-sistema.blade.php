@php
    use App\Support\Erp\EmpresaParametros;

    $booleans = EmpresaParametros::sistemaBooleanFields();
    $backupStatus = EmpresaParametros::sistemaBackupStatusOptions();
@endphp

<div class="erp-empresas-api-servicos">
    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Atualização do sistema</h3>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Link HTTPS direto do <strong>Unitec-ERP-Update.zip</strong>. O serviço verifica,
            baixa e prepara a atualização automaticamente para confirmação no login.
            Se vazio, usa o <code>.env</code> ou o canal padrão do GitHub.
        </p>

        <div class="erp-empresas-api-servicos__rows">
            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_update_download_url">Link do ZIP</label>
                <input
                    id="param-param_update_download_url"
                    type="url"
                    wire:model="data.param_update_download_url"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    placeholder="https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip"
                    spellcheck="false"
                    autocomplete="off"
                >
            </div>
        </div>
    </section>

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Backup automático</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            @foreach ($booleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Gravado na empresa. O painel principal usa o status do último backup abaixo.
        </p>

        <div class="erp-empresas-api-servicos__rows">
            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_backup_pasta_destino">Pasta destino</label>
                <input
                    id="param-param_backup_pasta_destino"
                    type="text"
                    wire:model="data.param_backup_pasta_destino"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    placeholder="C:\Backups\Unitec"
                    autocomplete="off"
                >
            </div>

            <div class="erp-empresas-api-servicos__row erp-empresas-api-servicos__row--2">
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_backup_intervalo_horas">Intervalo (horas)</label>
                    <input
                        id="param-param_backup_intervalo_horas"
                        type="number"
                        min="1"
                        max="168"
                        wire:model="data.param_backup_intervalo_horas"
                        class="erp-pcad-form__input erp-pcad-form__input--xs"
                    >
                </div>
                <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                    <label class="erp-pcad-form__label" for="param-param_backup_ultimo_em">Último backup em</label>
                    <input
                        id="param-param_backup_ultimo_em"
                        type="text"
                        wire:model="data.param_backup_ultimo_em"
                        class="erp-pcad-form__input erp-pcad-form__input--grow"
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_backup_ultimo_status">Status último BKP</label>
                <select
                    id="param-param_backup_ultimo_status"
                    wire:model="data.param_backup_ultimo_status"
                    class="erp-pcad-form__select erp-pcad-form__select--md"
                >
                    @foreach ($backupStatus as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field">
                <label class="erp-pcad-form__label" for="param-param_portal_bkp_token">Token Portal BKP</label>
                <input
                    id="param-param_portal_bkp_token"
                    type="text"
                    wire:model="data.param_portal_bkp_token"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    autocomplete="off"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    spellcheck="false"
                >
            </div>
        </div>
    </section>
</div>
