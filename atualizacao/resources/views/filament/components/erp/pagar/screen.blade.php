@php
    $searchFields = [
        'numero' => 'NÚMERO',
        'emissao' => 'EMISSÃO',
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
    ];

    if ($this->podeVerAbaDesdobramentos()) {
        $viewTabs['desdobramentos'] = 'Desdobramentos de Parcelas';
    }

    $pageSizeOptions = [25, 50, 100];
    $activeFields = count($this->searchFieldsActive) >= 2
        ? array_slice($this->searchFieldsActive, 0, 2)
        : ['fornecedor', 'vencimento'];
    $searchButtonLabel = collect($activeFields)
        ->map(fn (string $column): string => $searchFields[$column] ?? mb_strtoupper($column, 'UTF-8'))
        ->implode(' + ');
    $dateFields = ['emissao', 'vencimento', 'pago_em'];
    $hasDateSearch = collect($activeFields)->contains(
        fn (string $column): bool => in_array($column, $dateFields, true)
    );
    $placeholders = [
        'numero' => 'Digite o número',
        'documento' => 'Digite o documento',
        'fornecedor' => 'DIGITE O FORNECEDOR',
        'valor' => 'Digite o valor',
        'desconto' => 'Digite o desconto',
        'juros' => 'Digite os juros',
        'valor_pago' => 'Digite o valor pago',
        'saldo' => 'Digite o saldo',
    ];
@endphp

<div class="erp-pagar" wire:ignore.self>
    @if ($this->viewTab !== 'desdobramentos')
        <div class="erp-pagar__filter-block">
            <span class="erp-pagar__filter-title">Filtro</span>

            <div class="erp-pagar__locate-group">
                <span class="erp-pagar__locate-label">Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                    'markedFields' => $activeFields,
                    'buttonLabel' => $searchButtonLabel,
                    'wireMethod' => 'toggleSearchField',
                    'closeOnSelect' => false,
                ])

                @foreach ($activeFields as $fieldKey)
                    @continue(in_array($fieldKey, $dateFields, true))
                    @if ($fieldKey === 'fornecedor')
                        <span class="erp-pagar__locate-search-field erp-pagar__locate-search-field--fornecedor">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="localSearchByField.fornecedor"
                                wire:key="pagar-local-search-fornecedor"
                                wire:focus="openLocalFornecedorLookup"
                                wire:keydown.arrow-up.prevent="moveLocalFornecedorSelection(-1)"
                                wire:keydown.arrow-down.prevent="moveLocalFornecedorSelection(1)"
                                wire:keydown.enter.prevent="handleLocalFornecedorEnter"
                                wire:keydown.escape.prevent="closeLocalFornecedorLookup"
                                class="erp-pagar__input erp-pagar__search-text erp-pagar__search-text--fornecedor"
                                placeholder="{{ $placeholders['fornecedor'] }}"
                                autocomplete="off"
                                data-erp-uppercase
                            >
                            @if ($this->localFornecedorLookupOpen && filled($this->localSearchByField['fornecedor'] ?? null))
                                @if ($this->localFornecedorResults !== [])
                                    @include('filament.components.erp.shared.local-fornecedor-lookup-panel')
                                @else
                                    <div class="erp-cliente-filter-lookup erp-cliente-filter-lookup--empty">
                                        Nenhum fornecedor encontrado.
                                    </div>
                                @endif
                            @endif
                        </span>
                    @else
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="localSearchByField.{{ $fieldKey }}"
                            wire:key="pagar-local-search-{{ $fieldKey }}"
                            class="erp-pagar__input erp-pagar__search-text"
                            placeholder="{{ $placeholders[$fieldKey] ?? 'Digite para pesquisar' }}"
                            autocomplete="off"
                            @if ($fieldKey === 'documento') data-erp-uppercase @endif
                        >
                    @endif
                @endforeach

                @if ($hasDateSearch)
                    <div
                        class="erp-pagar__search-date-range"
                        wire:key="pagar-local-search-dates-{{ implode('-', $activeFields) }}"
                    >
                        <span class="erp-pagar__period-label">
                            <span class="erp-pagar__period-caption">de</span>
                            <span class="erp-pagar__date-wrap" x-data>
                                <input
                                    type="date"
                                    data-erp-native-date
                                    wire:model.live="localSearchDe"
                                    class="erp-pagar__period-input erp-pagar__search-date-from"
                                    @click="try { $el.showPicker() } catch (e) {}"
                                    @keydown.enter.prevent="try { $el.showPicker() } catch (e) {}"
                                >
                                <span class="erp-pagar__date-icon" aria-hidden="true"></span>
                            </span>
                        </span>
                        <span class="erp-pagar__period-label">
                            <span class="erp-pagar__period-caption">até</span>
                            <span class="erp-pagar__date-wrap" x-data>
                                <input
                                    type="date"
                                    data-erp-native-date
                                    wire:model.live="localSearchAte"
                                    class="erp-pagar__period-input erp-pagar__search-date-to"
                                    @click="try { $el.showPicker() } catch (e) {}"
                                    @keydown.enter.prevent="try { $el.showPicker() } catch (e) {}"
                                >
                                <span class="erp-pagar__date-icon" aria-hidden="true"></span>
                            </span>
                        </span>
                    </div>
                @endif
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
    @endif

    @if (count($viewTabs) > 1 || $this->viewTab === 'desdobramentos')
        <div class="erp-pagar__view-tabs">
            @foreach ($viewTabs as $value => $label)
                <button
                    type="button"
                    wire:click="setViewTab('{{ $value }}')"
                    @class(['erp-pagar__view-tab', 'erp-pagar__view-tab--active' => $this->viewTab === $value])
                >{{ $label }}</button>
            @endforeach
        </div>
    @endif

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
