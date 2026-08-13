@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'emissao' => 'EMISSÃO',
        'historico' => 'HISTÓRICO',
        'documento' => 'DOC.',
        'cliente' => 'CLIENTE',
        'vencimento' => 'VENCIMENTO',
        'valor' => 'VALOR',
        'desconto' => 'DESCONTO',
        'juros' => 'JUROS',
        'valor_recebido' => 'VL. RECEBIDO',
        'recebido_em' => 'RECEBIDO EM',
        'saldo' => 'SALDO',
    ];

    $viewTabs = [
        'dados' => 'Dados da Conta',
        'desdobramentos' => 'Desdobramentos de Parcelas',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-receber" wire:ignore.self>
    <div class="erp-receber__filter-block">
        <span class="erp-receber__filter-title">Filtro</span>

        <div class="erp-receber__locate-group">
            <span class="erp-receber__locate-label">Localizar</span>
            <select wire:model.live="searchColumn" class="erp-receber__select erp-receber__search-field">
                @foreach ($searchFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="erp-receber__locate-search-field">
                <input
                    type="text"
                    wire:model.live.debounce.250ms="localSearch"
                    wire:key="receber-local-search-{{ $this->searchColumn }}"
                    @if ($this->searchColumn === 'cliente')
                        wire:focus="openLocalClienteLookup"
                        wire:keydown.arrow-up.prevent="moveLocalClienteSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveLocalClienteSelection(1)"
                        wire:keydown.enter.prevent="handleLocalClienteEnter"
                        wire:keydown.escape.prevent="closeLocalClienteLookup"
                        data-erp-uppercase
                        placeholder="DIGITE O NOME DO CLIENTE"
                    @else
                        placeholder="DIGITE AQUI SUA PESQUISA"
                    @endif
                    class="erp-receber__input erp-receber__search-text"
                    autocomplete="off"
                >
                @if ($this->searchColumn === 'cliente' && $this->localClienteLookupOpen && filled($this->localSearch))
                    @if ($this->localClienteResults !== [])
                        @include('filament.components.erp.shared.local-cliente-lookup-panel')
                    @else
                        <div class="erp-cliente-filter-lookup erp-cliente-filter-lookup--empty">
                            Nenhum cliente encontrado.
                        </div>
                    @endif
                @endif
            </span>
        </div>

        <div
            class="erp-receber__period"
            data-erp-date-group
            data-erp-date-apply-method="applyPeriodoFilter"
        >
            <label class="erp-receber__period-label">
                de
                <input
                    type="date"
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    class="erp-receber__period-input erp-receber__period-from"
                >
            </label>
            <label class="erp-receber__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    class="erp-receber__period-input"
                >
            </label>
        </div>

        <div class="erp-receber__page-size-group">
            <label class="erp-receber__page-size-label">
                por página
                <select wire:model.live="tableRecordsPerPage" class="erp-receber__select erp-receber__page-size-select">
                    @foreach ($pageSizeOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    <div class="erp-receber__view-tabs">
        @foreach ($viewTabs as $value => $label)
            <button
                type="button"
                wire:click="setViewTab('{{ $value }}')"
                @class(['erp-receber__view-tab', 'erp-receber__view-tab--active' => $this->viewTab === $value])
            >{{ $label }}</button>
        @endforeach
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
