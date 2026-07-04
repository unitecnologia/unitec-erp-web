@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'emissao' => 'EMISSÃO',
        'produto' => 'PRODUTO',
        'documento' => 'DOC',
        'fornecedor' => 'FORNECEDOR',
        'vencimento' => 'VENCIMENTO',
        'valor' => 'VALOR',
        'desconto' => 'DESCONTO',
        'juros' => 'JUROS',
        'valor_pago' => 'VL. PAGO',
        'pago_em' => 'PAGO EM',
        'saldo' => 'SALDO',
    ];

    $viewTabs = [
        'titulos' => 'Títulos',
        'desdobramentos' => 'Desdobramentos de Parcelas',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-pagar" wire:ignore.self>
    <div class="erp-pagar__filter-block">
        <span class="erp-pagar__filter-title">Filtro</span>

        <label class="erp-pagar__supplier-label">
            Selecione Fornecedor
            <select wire:model.live="fornecedorFilter" class="erp-pagar__select erp-pagar__supplier-select">
                <option value="todos">&lt;Todos os fornecedores&gt;</option>
                @foreach ($this->fornecedoresOptions as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <div class="erp-pagar__period">
            <label class="erp-pagar__period-label">
                de
                <input
                    type="date"
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    class="erp-pagar__period-input erp-pagar__period-from"
                >
            </label>
            <label class="erp-pagar__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    class="erp-pagar__period-input"
                >
            </label>
            <button
                type="button"
                wire:click="applyPeriodFilter"
                onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-pagar') ?? document)"
                class="erp-pagar__btn"
            >
                Filtrar Período
            </button>
        </div>

        <div class="erp-pagar__locate-group">
            <span class="erp-pagar__locate-label">Localizar</span>
            <select wire:model.live="searchColumn" class="erp-pagar__select erp-pagar__search-field">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input
                type="text"
                wire:model.live.debounce.300ms="localSearch"
                wire:key="pagar-local-search-{{ $this->searchColumn }}"
                class="erp-pagar__input erp-pagar__search-text"
                placeholder="DIGITE AQUI SUA PESQUISA"
            >
        </div>

        <div class="erp-pagar__page-size-group">
            <label class="erp-pagar__page-size-label">
                por página
                <select wire:model.live="tableRecordsPerPage" class="erp-pagar__select erp-pagar__page-size-select">
                    @foreach ($pageSizeOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div class="erp-pagar__view-tabs">
        @foreach ($viewTabs as $value => $label)
            <button
                type="button"
                wire:click="setViewTab('{{ $value }}')"
                @class(['erp-pagar__view-tab', 'erp-pagar__view-tab--active' => $this->viewTab === $value])
            >{{ $label }}</button>
        @endforeach
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
