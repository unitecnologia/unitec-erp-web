@php
    $searchFields = [
        'emissao' => 'EMISSÃO',
        'documento' => 'PEDIDO',
        'historico' => 'HISTÓRICO',
        'plano_contas' => 'PLANO DE CONTAS',
        'conta' => 'CONTAS',
        'entrada' => 'ENTRADA',
        'saida' => 'SAÍDA',
    ];

    $activeFields = array_values(array_unique(array_filter(
        [$this->searchColumn, $this->searchColumn2],
        fn (string $column): bool => $column !== '' && array_key_exists($column, $searchFields),
    )));
    if ($activeFields === []) {
        $activeFields = ['documento'];
    }
    $searchButtonLabel = implode(' + ', array_map(fn (string $column): string => $searchFields[$column], $activeFields));
    $placeholders = [
        'emissao' => 'dd/mm/aaaa',
        'documento' => 'DIGITE O PEDIDO',
        'historico' => 'DIGITE O HISTÓRICO',
        'plano_contas' => 'DIGITE O PLANO DE CONTAS',
        'conta' => 'DIGITE A CONTA',
        'entrada' => 'DIGITE O VALOR',
        'saida' => 'DIGITE O VALOR',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-caixa" wire:ignore.self>
    <div class="erp-caixa__filter-block">
        <label class="erp-caixa__account-label">
            Selecione Conta
            <select wire:model.live="contaFilter" class="erp-caixa__select erp-caixa__account-select">
                @foreach ($this->contasOptions as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <div
            class="erp-caixa__period"
            wire:ignore
            data-erp-date-group
            data-erp-date-auto-apply="1"
            data-erp-date-apply-method="applyPeriodFilterAuto"
        >
            <label class="erp-caixa__period-label">
                Período de
                <input
                    type="date"
                    data-erp-date
                    data-wire-field="periodoDe"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $this->periodoDe }}"
                    value="{{ $this->periodoDe }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    class="erp-caixa__period-input erp-caixa__period-from"
                >
            </label>
            <label class="erp-caixa__period-label">
                até
                <input
                    type="date"
                    data-erp-date
                    data-wire-field="periodoAte"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $this->periodoAte }}"
                    value="{{ $this->periodoAte }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    class="erp-caixa__period-input"
                >
            </label>
        </div>

        <div class="erp-caixa__locate-group">
            <span class="erp-caixa__locate-label">Localizar</span>
            @include('filament.components.erp.shared.search-field-dropdown', [
                'fields' => $searchFields,
                'searchColumn' => $this->searchColumn,
                'markedFields' => $activeFields,
                'buttonLabel' => $searchButtonLabel,
                'wireMethod' => 'toggleSearchField',
                'closeOnSelect' => false,
            ])
            @foreach ($activeFields as $index => $column)
                @php $model = $index === 0 ? 'localSearch' : 'localSearch2'; @endphp
                <input
                    type="text"
                    wire:model.live.debounce.400ms="{{ $model }}"
                    wire:key="caixa-local-search-{{ $model }}-{{ $column }}"
                    class="erp-caixa__input erp-caixa__locate-input"
                    placeholder="{{ $placeholders[$column] ?? 'DIGITE AQUI SUA PESQUISA' }}"
                    autocomplete="off"
                    @if (in_array($column, ['entrada', 'saida'], true)) inputmode="decimal" @endif
                >
            @endforeach
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
