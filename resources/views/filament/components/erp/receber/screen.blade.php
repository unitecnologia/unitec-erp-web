@php
    $searchFields = [
        'emissao' => 'EMISSÃO',
        'historico' => 'HISTÓRICO',
        'documento' => 'PEDIDO',
        'cliente' => 'CLIENTE',
        'vencimento' => 'VENCIMENTO',
        'valor' => 'VALOR',
        'desconto' => 'DESCONTO',
        'juros' => 'JUROS',
        'valor_recebido' => 'V.RECEBIDO',
        'recebido_em' => 'RECEBIDO EM',
        'saldo' => 'SALDO',
    ];

    $viewTabs = [
        'dados' => 'Dados da Conta',
        'desdobramentos' => 'Desdobramentos de Parcelas',
    ];

    $pageSizeOptions = [25, 50, 100];
    $activeFields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn ?: 'cliente'];
    $searchButtonLabel = collect($activeFields)
        ->map(fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'))
        ->implode(' + ');
    $clienteLookup = $activeFields === ['cliente'];
@endphp

<div class="erp-receber" wire:ignore.self>
    @if ($this->viewTab !== 'desdobramentos')
    <div class="erp-receber__filter-block">
        <span class="erp-receber__filter-title">Filtro</span>

        <div class="erp-receber__locate-group">
            <span class="erp-receber__locate-label">Localizar</span>
            @include('filament.components.erp.shared.search-field-dropdown', [
                'fields' => $searchFields,
                'searchColumn' => $this->searchColumn,
                'markedFields' => $activeFields,
                'buttonLabel' => $searchButtonLabel,
                'wireMethod' => 'toggleSearchField',
                'closeOnSelect' => false,
            ])
            <span
                class="erp-receber__locate-search-field"
                @if ($clienteLookup)
                    x-data="{
                        hideClienteLookup() {
                            this.$root.querySelectorAll('.erp-cliente-filter-lookup').forEach((el) => {
                                el.style.display = 'none';
                            });
                        }
                    }"
                @endif
            >
                <input
                    type="text"
                    wire:model.live.debounce.150ms="localSearch"
                    wire:key="receber-local-search-{{ $clienteLookup ? 'cliente' : 'texto' }}"
                    @if ($clienteLookup)
                        wire:focus="openLocalClienteLookup"
                        wire:keydown.arrow-up.prevent="moveLocalClienteSelection(-1)"
                        wire:keydown.arrow-down.prevent="moveLocalClienteSelection(1)"
                        x-on:keydown.enter.prevent="hideClienteLookup(); $wire.handleLocalClienteEnter()"
                        x-on:keydown.escape.prevent="hideClienteLookup(); $wire.closeLocalClienteLookup()"
                        x-on:input="if (String($event.target.value || '').trim() === '') { hideClienteLookup(); $wire.closeLocalClienteLookup() }"
                        data-erp-uppercase
                        placeholder="DIGITE O NOME DO CLIENTE"
                    @else
                        placeholder="DIGITE AQUI SUA PESQUISA"
                    @endif
                    class="erp-receber__input erp-receber__search-text"
                    autocomplete="off"
                >
                @if ($clienteLookup && $this->localClienteLookupOpen && filled($this->localSearch))
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
                    data-erp-date-initial="{{ $this->periodoDe }}"
                    value="{{ $this->periodoDe }}"
                    class="erp-receber__period-input erp-receber__period-from"
                >
            </label>
            <label class="erp-receber__period-label">
                até
                <input
                    type="date"
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $this->periodoAte }}"
                    value="{{ $this->periodoAte }}"
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

    @endif

    <div class="erp-receber__view-tabs">
        @foreach ($viewTabs as $value => $label)
            <button
                type="button"
                class="erp-receber__view-tab {{ ($this->viewTab ?: 'dados') === $value ? 'erp-receber__view-tab--active' : '' }}"
                wire:click="setViewTab('{{ $value }}')"
            >{{ $label }}</button>
        @endforeach
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
