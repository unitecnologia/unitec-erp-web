@php
    $isSeriais = $this->isSeriaisView();

    $searchFields = $isSeriais
        ? [
            'descricao' => 'DESCRIÇÃO',
            'numero_serie' => 'Nº SÉRIE',
        ]
        : [
            'codigo' => 'CÓDIGO',
            'referencia' => 'REFERÊNCIA',
            'codigo_barras' => 'CÓD. BARRAS',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'preco_venda' => 'PREÇO VENDA',
            'estoque' => 'QTD ATUAL',
            'localizacao' => 'LOCALIZAÇÃO',
        ];

    $pageSizeOptions = [25, 50, 100];
    $activeSearchFields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];
    $searchButtonLabel = implode(' + ', array_map(
        fn (string $key): string => $searchFields[$key] ?? $key,
        $activeSearchFields,
    ));
    $numericSearch = $activeSearchFields !== [] && array_diff($activeSearchFields, ['codigo']) === [];
    $decimalSearch = $activeSearchFields !== [] && array_diff($activeSearchFields, ['preco_venda', 'estoque']) === [];
@endphp

<div
    class="erp-produtos"
    wire:ignore.self
    @if ($this->erpListSyncPollEnabled())
        wire:poll.{{ $this->erpListSyncPollIntervalSeconds() }}s.visible="pollErpListSync"
    @endif
>
    <div class="erp-produtos__filters">
        <div class="erp-produtos__filters-row">
            <div class="erp-produtos__search-group">
                <span class="erp-produtos__locate-label">F6 | Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                    'markedFields' => $activeSearchFields,
                    'buttonLabel' => $searchButtonLabel,
                    'closeOnSelect' => false,
                ])
                <input
                    type="text"
                    data-erp-produtos-search
                    value="{{ $this->localSearch }}"
                    wire:key="produtos-local-search-{{ implode('-', $activeSearchFields) }}-{{ $this->viewFilter }}"
                    class="erp-produtos__input erp-produtos__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($numericSearch) inputmode="numeric" @endif
                    @if ($decimalSearch) inputmode="decimal" @endif
                >
            </div>

            <div class="erp-produtos__page-size-group">
                <label class="erp-produtos__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-produtos__select erp-produtos__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.produtos.tabs')

    <p class="erp-produtos__hint">
        @if ($isSeriais)
            Use as setas para navegar na lista.
        @else
            Clique na tecla [DELETE] para excluir Produto.
        @endif
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
    <script src="{{ asset('js/erp-produtos-search.js') }}?v={{ \App\Support\Erp\ErpAssetVersion::bundle() }}" defer data-navigate-track></script>
</div>
