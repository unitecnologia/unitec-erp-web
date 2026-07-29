@php
    use App\Support\Erp\EmpresaParametros;

    $numericFields = EmpresaParametros::numericFields();
    $columns = EmpresaParametros::numericColumnsByGroup();
    $subTabs = EmpresaParametros::parametrosSubTabs();
    $densityOptions = EmpresaParametros::sistemaUiDensityOptions();

    $subTabsSemNumericos = ['imposto', 'difal', 'pix', 'boleto', 'api_servicos', 'whatsapp', 'portal_contador', 'expedicao', 'estoques', 'sistema'];
    $mostrarNumericos = ! in_array($this->activeParametrosSubTab, $subTabsSemNumericos, true);
@endphp

<div class="erp-empresas-parametros">
    @if ($mostrarNumericos)
    <div class="erp-empresas-parametros__top">
        @foreach ($columns as $columnKey => $fieldKeys)
            <div class="erp-empresas-parametros__col">
                @foreach ($fieldKeys as $field)
                    @php($meta = $numericFields[$field])
                    <div @class([
                        'erp-empresas-parametros__field',
                        'erp-empresas-parametros__field--wide' => $field === 'param_pdv_marquee_texto',
                    ])>
                        <label
                            class="erp-pcad-form__label"
                            for="param-{{ $field }}"
                            @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif
                        >{{ $meta['label'] }}</label>
                        <input
                            id="param-{{ $field }}"
                            type="text"
                            wire:model="data.{{ $field }}"
                            @class([
                                'erp-pcad-form__input',
                                'erp-pcad-form__input--xs' => ($meta['type'] ?? '') === 'integer',
                                'erp-pcad-form__input--sm' => ($meta['type'] ?? '') !== 'integer',
                            ])
                        >
                    </div>
                @endforeach

                @if ($columnKey === 'col3')
                    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--select">
                        <label
                            class="erp-pcad-form__label"
                            for="param-param_ui_density"
                            title="Tamanho base da letra do sistema (px). Salve e atualize a página (F5)."
                        >Tamanho da letra</label>
                        <select id="param-param_ui_density" wire:model="data.param_ui_density" class="erp-pcad-form__select erp-pcad-form__select--sm">
                            @foreach ($densityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <div class="erp-empresas-parametros__subtabs">
        @foreach ($subTabs as $value => $label)
            <button
                type="button"
                wire:click="setActiveParametrosSubTab('{{ $value }}')"
                @class([
                    'erp-empresas-parametros__subtab',
                    'erp-empresas-parametros__subtab--active' => $this->activeParametrosSubTab === $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="erp-empresas-parametros__subpanel">
        @if ($this->activeParametrosSubTab === 'permissoes')
            @include('filament.components.erp.empresas.form.tabs.parametros-permissoes')
        @elseif ($this->activeParametrosSubTab === 'expedicao')
            @include('filament.components.erp.empresas.form.tabs.parametros-expedicao')
        @elseif ($this->activeParametrosSubTab === 'imposto')
            @include('filament.components.erp.empresas.form.tabs.parametros-imposto')
        @elseif ($this->activeParametrosSubTab === 'difal')
            @include('filament.components.erp.empresas.form.tabs.parametros-difal')
        @elseif ($this->activeParametrosSubTab === 'pix')
            @include('filament.components.erp.empresas.form.tabs.parametros-pix')
        @elseif ($this->activeParametrosSubTab === 'boleto')
            @include('filament.components.erp.empresas.form.tabs.parametros-boleto')
        @elseif ($this->activeParametrosSubTab === 'api_servicos')
            @include('filament.components.erp.empresas.form.tabs.parametros-api-servicos')
        @elseif ($this->activeParametrosSubTab === 'whatsapp')
            @include('filament.components.erp.empresas.form.tabs.parametros-whatsapp')
        @elseif ($this->activeParametrosSubTab === 'portal_contador')
            @include('filament.components.erp.empresas.form.tabs.parametros-portal-contador')
        @elseif ($this->activeParametrosSubTab === 'estoques')
            @include('filament.components.erp.empresas.form.tabs.parametros-estoques')
        @elseif ($this->activeParametrosSubTab === 'sistema')
            @include('filament.components.erp.empresas.form.tabs.parametros-sistema')
        @endif
    </div>
</div>
