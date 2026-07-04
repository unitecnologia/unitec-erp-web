@php
    $formTabs = [
        'dados' => 'Dados Básicos',
        'impostos' => 'Impostos',
        'promocao' => 'Promoção',
        'adicionais' => 'Adicionais',
        'foto' => 'Foto',
    ];

    $parametros = [
        ['field' => 'ativo', 'label' => 'Ativo', 'disabled' => false],
        ['field' => 'is_fiscal', 'label' => 'É Fiscal', 'disabled' => false],
        ['field' => 'tributacao_monofasica', 'label' => 'Tributação Monofásica', 'disabled' => false],
        ['field' => 'paga_comissao', 'label' => 'Paga Comissão', 'disabled' => false],
        ['field' => 'preco_variavel', 'label' => 'Preço Variavel', 'disabled' => false],
        ['field' => 'is_composicao', 'label' => 'Composição', 'disabled' => false],
        ['field' => 'is_servico', 'label' => 'Serviço', 'disabled' => false],
        ['field' => 'is_grade', 'label' => 'Grade', 'disabled' => false],
        ['field' => 'usa_tab_preco', 'label' => 'Usar Tab. Preço', 'disabled' => false],
        ['field' => 'is_combustivel', 'label' => 'Combustível', 'disabled' => false],
        ['field' => 'usa_imei', 'label' => 'Usa IMEI', 'disabled' => false],
        ['field' => 'contr_est_grade', 'label' => 'Contr. Est. Grade', 'disabled' => false],
        ['field' => 'mostrar_no_app', 'label' => 'Mostrar no App', 'disabled' => false],
    ];
@endphp

<div class="erp-pcad erp-produtos-pcad erp-produtos-pcad--pdv">
    <div class="erp-pcad__tabs">
        @foreach ($formTabs as $value => $label)
            <button
                type="button"
                wire:click="setActiveFormTab('{{ $value }}')"
                @class(['erp-pcad__tab', 'erp-pcad__tab--active' => $this->activeFormTab === $value])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div @class([
        'erp-pcad__workspace',
        'erp-pcad__workspace--dados' => $this->activeFormTab === 'dados',
    ])>
        <div class="erp-pcad__content">
            @if ($this->activeFormTab === 'dados')
                @include('filament.components.erp.produtos.form.tabs.dados-basicos-pdv')
            @elseif ($this->activeFormTab === 'impostos')
                @include('filament.components.erp.produtos.form.tabs.impostos')
            @elseif ($this->activeFormTab === 'promocao')
                @include('filament.components.erp.produtos.form.tabs.promocao')
            @elseif ($this->activeFormTab === 'foto')
                @include('filament.components.erp.produtos.form.tabs.foto-pdv')
            @else
                <p class="erp-produtos-pcad__panel-hint">Conteúdo em implementação.</p>
            @endif
        </div>

        @if ($this->activeFormTab === 'dados')
            <fieldset class="erp-pcad__group">
                <legend class="erp-pcad__group-title">Parâmetros</legend>
                <div class="erp-pcad__checks">
                    @foreach ($parametros as $param)
                        <label @class(['erp-pcad__check', 'erp-pcad__check--disabled' => $param['disabled']])>
                            <input
                                type="checkbox"
                                wire:model="data.{{ $param['field'] }}"
                                @disabled($param['disabled'])
                            >
                            <span>{{ $param['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </div>

    @include('filament.components.erp.produtos.form.lookup-modal')
    @include('filament.components.erp.produtos.form.duplicate-confirm-modal')
</div>
