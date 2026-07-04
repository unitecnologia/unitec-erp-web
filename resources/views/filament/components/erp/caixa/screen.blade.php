@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'emissao' => 'EMISSÃO',
        'documento' => 'DOCUMENTO',
        'historico' => 'HISTÓRICO',
        'plano_contas' => 'PLANO DE CONTAS',
        'conta' => 'CONTAS',
        'entrada' => 'ENTRADA',
        'saida' => 'SAÍDA',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-caixa" wire:ignore.self>
    <div class="erp-caixa__filter-block">
        <label class="erp-caixa__account-label">
            Selecione Conta
            <select wire:model.live="contaFilter" class="erp-caixa__select erp-caixa__account-select">
                <option value="todas">&lt;todas as contas&gt;</option>
                @foreach ($this->contasOptions as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <div class="erp-caixa__period">
            <label class="erp-caixa__period-label">
                Período de
                <input
                    type="date"
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    class="erp-caixa__period-input erp-caixa__period-from"
                >
            </label>
            <label class="erp-caixa__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    class="erp-caixa__period-input"
                >
            </label>
            <button
                type="button"
                wire:click="applyPeriodFilter"
                onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-caixa') ?? document)"
                class="erp-caixa__btn"
            >
                Filtrar Período
            </button>
        </div>

        <div class="erp-caixa__locate-group">
            <span class="erp-caixa__locate-label">Localizar</span>
            <select wire:model.live="searchColumn" class="erp-caixa__select erp-caixa__locate-field">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input
                type="text"
                wire:model.live.debounce.300ms="localSearch"
                wire:key="caixa-local-search-{{ $this->searchColumn }}"
                class="erp-caixa__input erp-caixa__locate-input"
                placeholder="DIGITE AQUI SUA PESQUISA"
            >
        </div>

        <div class="erp-caixa__page-size-group">
            <label class="erp-caixa__page-size-label">
                por página
                <select wire:model.live="tableRecordsPerPage" class="erp-caixa__select erp-caixa__page-size-select">
                    @foreach ($pageSizeOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
