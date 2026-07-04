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
@endphp

<div class="erp-produtos" wire:ignore.self>
    <div class="erp-produtos__filters">
        <div class="erp-produtos__filters-row">
            <div class="erp-produtos__search-group">
                <span class="erp-produtos__locate-label">F6 | Localizar</span>
                <select wire:model.live="searchColumn" class="erp-produtos__select erp-produtos__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="produtos-local-search-{{ $this->searchColumn }}-{{ $this->viewFilter }}"
                    class="erp-produtos__input erp-produtos__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                    @if (in_array($this->searchColumn, ['preco_venda', 'estoque'], true)) inputmode="decimal" @endif
                >
            </div>

            <div class="erp-produtos__search-actions">
                <button type="button" wire:click="search" class="erp-produtos__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-produtos__btn erp-produtos__btn--secondary">Limpar</button>
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
            Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.
        @else
            Clique na tecla [DELETE] para excluir Produto.
        @endif
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
