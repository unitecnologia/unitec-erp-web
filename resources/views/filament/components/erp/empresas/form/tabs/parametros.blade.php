@php
    use App\Support\Erp\EmpresaParametros;

    $numericFields = EmpresaParametros::numericFields();
    $planoFields = EmpresaParametros::planoContaFields();
    $blocos = EmpresaParametros::parametrosTopoBlocos();
    $subTabs = EmpresaParametros::parametrosSubTabs();
    $densityOptions = EmpresaParametros::sistemaUiDensityOptions();

    $subTabsSemNumericos = ['imposto', 'difal', 'pix', 'boleto', 'api_servicos', 'whatsapp', 'email', 'portal_contador', 'mercado_livre', 'ifood', 'estoques', 'sistema'];
    $mostrarNumericos = ! in_array($this->activeParametrosSubTab, $subTabsSemNumericos, true);

    $boletoBancoForm = preg_replace('/\D/', '', (string) (($this->boletoContaForm['banco'] ?? '') ?: '')) ?: '';
@endphp

<div class="erp-empresas-parametros">
    @if ($mostrarNumericos)
    <div class="erp-empresas-parametros__blocks">
        @foreach ($blocos as $bloco)
            <fieldset class="erp-pcad__group erp-empresas-parametros__block">
                <legend class="erp-pcad__group-title">{{ $bloco['title'] }}</legend>

                @foreach ($bloco['numeric'] ?? [] as $field)
                    @php($meta = $numericFields[$field])
                    <div class="erp-empresas-parametros__item">
                        <div class="erp-empresas-parametros__item-row">
                            <label class="erp-pcad-form__label" for="param-{{ $field }}">
                                {{ $meta['label'] }}
                                @if (filled($meta['hint'] ?? null))
                                    <span
                                        class="erp-produtos-impostos__label--hint"
                                        title="{{ $meta['hint'] }}"
                                        role="img"
                                        aria-label="{{ $meta['hint'] }}"
                                    ></span>
                                @endif
                            </label>
                            <input
                                id="param-{{ $field }}"
                                type="text"
                                wire:model="data.{{ $field }}"
                                @if (($meta['type'] ?? '') === 'integer')
                                    data-mask="integer"
                                    inputmode="numeric"
                                @endif
                                @class([
                                    'erp-pcad-form__input',
                                    'erp-pcad-form__input--xs' => ($meta['type'] ?? '') === 'integer',
                                    'erp-pcad-form__input--sm' => ($meta['type'] ?? '') !== 'integer',
                                ])
                            >
                        </div>
                    </div>
                @endforeach

                @foreach ($bloco['planos'] ?? [] as $field)
                    @php($meta = $planoFields[$field])
                    @php($selectedId = (int) ($this->data[$field] ?? 0))
                    <div class="erp-empresas-parametros__item">
                        <label class="erp-pcad-form__label" for="param-{{ $field }}">
                            {{ $meta['label'] }}
                            <span
                                class="erp-produtos-impostos__label--hint"
                                title="{{ $meta['hint'] }}"
                                role="img"
                                aria-label="{{ $meta['hint'] }}"
                            ></span>
                        </label>
                        <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select">
                            <option value="">Selecione</option>
                            @foreach (EmpresaParametros::planoContaOptions($meta['dc'], $selectedId > 0 ? $selectedId : null) as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                            </select>
                    </div>
                @endforeach

                @if ($bloco['density'] ?? false)
                    <div class="erp-empresas-parametros__item">
                        <div class="erp-empresas-parametros__item-row erp-empresas-parametros__item-row--select">
                            <label class="erp-pcad-form__label" for="param-param_ui_density">
                                Tamanho da letra
                                <span
                                    class="erp-produtos-impostos__label--hint"
                                    title="Define o tamanho padrão da fonte nas telas do ERP."
                                    role="img"
                                    aria-label="Define o tamanho padrão da fonte nas telas do ERP."
                                ></span>
                            </label>
                            <select id="param-param_ui_density" wire:model="data.param_ui_density" class="erp-pcad-form__select erp-pcad-form__select--sm">
                                @foreach ($densityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            </fieldset>
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
        @elseif ($this->activeParametrosSubTab === 'email')
            @include('filament.components.erp.empresas.form.tabs.parametros-email')
        @elseif ($this->activeParametrosSubTab === 'portal_contador')
            @include('filament.components.erp.empresas.form.tabs.parametros-portal-contador')
        @elseif ($this->activeParametrosSubTab === 'mercado_livre')
            @include('filament.components.erp.empresas.form.tabs.parametros-mercado-livre')
        @elseif ($this->activeParametrosSubTab === 'ifood')
            @include('filament.components.erp.empresas.form.tabs.parametros-ifood')
        @elseif ($this->activeParametrosSubTab === 'estoques')
            @include('filament.components.erp.empresas.form.tabs.parametros-estoques')
        @elseif ($this->activeParametrosSubTab === 'sistema')
            @include('filament.components.erp.empresas.form.tabs.parametros-sistema')
        @endif
    </div>
</div>

@include('filament.components.erp.empresas.form.boleto-conta-modal', [
    'bancos' => EmpresaParametros::boletoBancoOptions(),
    'ambientes' => EmpresaParametros::boletoAmbienteOptions(),
    'especies' => EmpresaParametros::boletoEspecieOptions(),
    'posVencimento' => EmpresaParametros::boletoPosVencimentoOptions(),
    'isAilosForm' => $boletoBancoForm === EmpresaParametros::BOLETO_BANCO_AILOS,
    'isSicrediForm' => $boletoBancoForm === EmpresaParametros::BOLETO_BANCO_SICREDI,
    'ailosCallbackUrl' => EmpresaParametros::boletoAilosAuthCallbackUrl(),
])
