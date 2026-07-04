@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome' => 'NOME',
        'cnpj_cpf' => 'CNPJ/CPF',
        'cidade' => 'CIDADE',
        'email' => 'EMAIL',
        'fone' => 'FONE',
    ];

    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-contadores" wire:ignore.self>
    <div class="erp-contadores__filters">
        <div class="erp-contadores__filters-row">
            <div class="erp-contadores__search-group">
                <span class="erp-contadores__locate-label">F6 | Localizar</span>
                <select wire:model.live="searchColumn" class="erp-contadores__select erp-contadores__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="contadores-local-search-{{ $this->searchColumn }}"
                    class="erp-contadores__input erp-contadores__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($this->searchColumn === 'nome') data-erp-uppercase @endif
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                >
            </div>

            <div class="erp-contadores__search-actions">
                <button type="button" wire:click="search" class="erp-contadores__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-contadores__btn erp-contadores__btn--secondary">Limpar</button>
            </div>

            <div class="erp-contadores__page-size-group">
                <label class="erp-contadores__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-contadores__select erp-contadores__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="erp-contadores__hint">
        Clique na tecla [DELETE] para excluir contador.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @php
        use App\Support\Erp\ErpAssetVersion;
        $jsVersion = ErpAssetVersion::bundle();
    @endphp
    <script src="{{ asset('js/erp-contadores.js') }}?v={{ $jsVersion }}" defer></script>
</div>
