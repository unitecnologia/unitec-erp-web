@php
    use App\Models\Empresa;
    use App\Models\Product;
    use App\Support\Erp\ErpContext;

    $jsPath = public_path('js/erp-produtos-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
    $masksJsPath = public_path('js/erp-masks.js');
    $masksJsVersion = file_exists($masksJsPath) ? filemtime($masksJsPath) : time();

    $status = ErpContext::statusBar();
    $empresaId = session('erp_empresa_id', auth()->user()?->empresa_id);
    $empresas = Empresa::query()->where('ativo', true)->orderBy('nome')->get();

    $dataCadastro = isset($this->record?->created_at)
        ? $this->record->created_at->format('d/m/Y')
        : '';

    $parametros = [
        ['field' => 'ativo', 'label' => 'Ativo', 'disabled' => false],
        ['field' => 'is_fiscal', 'label' => 'É Fiscal', 'disabled' => false],
        ['field' => 'tributacao_monofasica', 'label' => 'Tributação Monofásica', 'disabled' => false],
        ['field' => 'paga_comissao', 'label' => 'Paga Comissão', 'disabled' => false],
        ['field' => 'preco_variavel', 'label' => 'Preço Variavel', 'disabled' => false],
        ['field' => 'is_composicao', 'label' => 'Composição', 'disabled' => ! $this->isEditingProduct()],
        ['field' => 'is_servico', 'label' => 'Serviço', 'disabled' => false],
        ['field' => 'is_grade', 'label' => 'Grade', 'disabled' => ! $this->isEditingProduct()],
        ['field' => 'usa_tab_preco', 'label' => 'Usar Tab. Preço', 'disabled' => false],
        ['field' => 'is_combustivel', 'label' => 'Combustível', 'disabled' => false],
        ['field' => 'usa_imei', 'label' => 'Usa IMEI', 'disabled' => false],
        ['field' => 'contr_est_grade', 'label' => 'Contr. Est. Grade', 'disabled' => false],
        ['field' => 'mostrar_no_app', 'label' => 'Mostrar no App', 'disabled' => false],
    ];
@endphp

@if ($this->embedsInPdv)
    @include('filament.components.erp.produtos.form.shell-pdv')
@else
<div class="erp-pcad erp-produtos-pcad">
    <div class="erp-produtos-pcad__top">
        <fieldset class="erp-produtos-pcad__empresa-box">
            <legend class="erp-produtos-pcad__fieldset-legend">Selecione empresa</legend>
            <select class="erp-pcad-form__select erp-produtos-pcad__empresa-select" disabled>
                @forelse ($empresas as $empresa)
                    <option value="{{ $empresa->id }}" @selected((int) $empresaId === (int) $empresa->id)>
                        {{ $empresa->nome }}
                    </option>
                @empty
                    <option>{{ $status['Empresa'] ?? '—' }}</option>
                @endforelse
            </select>
        </fieldset>

        <div class="erp-produtos-pcad__cadastro-em">
            <label class="erp-produtos-pcad__cadastro-label" for="pprod-data-cadastro">Este produto foi cadastrado em</label>
            <input
                id="pprod-data-cadastro"
                type="text"
                value="{{ $dataCadastro }}"
                readonly
                class="erp-pcad-form__input erp-produtos-pcad__cadastro-input"
            >
        </div>

        <div class="erp-produtos-pcad__brand" aria-hidden="true">
            <span class="erp-produtos-pcad__brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="M3.3 7.7 12 12.5l8.7-4.8M12 22V12.5"/>
                </svg>
            </span>
        </div>
    </div>

    <div class="erp-produtos-pcad__workspace">
        <div class="erp-produtos-pcad__fields">
            @include('filament.components.erp.produtos.form.tabs.dados-basicos')
        </div>

        <div class="erp-produtos-pcad__lower">
            <div class="erp-produtos-pcad__tabs-col">
                <div class="erp-produtos-pcad__tabs-area">
                    <div class="erp-produtos-pcad__bottom-tabs">
                        @foreach ($this->visibleProductFormTabs as $tab)
                            <button
                                type="button"
                                wire:click="setActiveFormTab('{{ $tab['key'] }}')"
                                @class(['erp-produtos-pcad__bottom-tab', 'erp-produtos-pcad__bottom-tab--active' => $this->activeFormTab === $tab['key']])
                            >{{ $tab['label'] }}</button>
                        @endforeach
                    </div>

                    <div class="erp-produtos-pcad__bottom-panel">
                        @if ($this->activeFormTab === 'impostos')
                            @include('filament.components.erp.produtos.form.tabs.impostos')
                        @elseif ($this->activeFormTab === 'promocao')
                            @include('filament.components.erp.produtos.form.tabs.promocao')
                        @elseif ($this->activeFormTab === 'adicionais')
                            @include('filament.components.erp.produtos.form.tabs.adicionais')
                        @elseif ($this->activeFormTab === 'combustivel')
                            @include('filament.components.erp.produtos.form.tabs.combustivel')
                        @elseif ($this->activeFormTab === 'balanca')
                            @include('filament.components.erp.produtos.form.tabs.balanca')
                        @elseif ($this->activeFormTab === 'grade')
                            @include('filament.components.erp.produtos.form.tabs.grade')
                        @elseif ($this->activeFormTab === 'imei')
                            @include('filament.components.erp.produtos.form.tabs.imei')
                        @elseif ($this->activeFormTab === 'composicao')
                            @include('filament.components.erp.produtos.form.tabs.composicao')
                        @elseif ($this->activeFormTab === 'tabela_preco')
                            @include('filament.components.erp.produtos.form.tabs.tabela-preco')
                        @elseif ($this->activeFormTab === 'ultimos_precos')
                            @include('filament.components.erp.produtos.form.tabs.ultimos-precos')
                        @else
                            <p class="erp-produtos-pcad__panel-hint">Conteúdo da aba {{ str_replace('_', ' ', $this->activeFormTab) }} em implementação.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="erp-produtos-pcad__foto-col">
                @include('filament.components.erp.produtos.form.product-foto')
            </div>

            <aside class="erp-produtos-pcad__aside">
                <fieldset class="erp-pcad__group erp-produtos-pcad__params">
                    <legend class="erp-pcad__group-title">Parâmetros</legend>
                    <div class="erp-pcad__checks">
                        @foreach ($parametros as $param)
                            <label @class(['erp-pcad__check', 'erp-pcad__check--disabled' => $param['disabled']])>
                                <input
                                    type="checkbox"
                                    wire:model.live="data.{{ $param['field'] }}"
                                    @disabled($param['disabled'])
                                >
                                <span>{{ $param['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </aside>
        </div>
    </div>

    @include('filament.components.erp.produtos.form.lookup-modal')
    @include('filament.components.erp.produtos.form.duplicate-confirm-modal')
</div>
@endif

@include('filament.components.erp.form-scripts')
<script src="{{ asset('js/erp-produtos-form.js') }}?v={{ $jsVersion }}" defer></script>
