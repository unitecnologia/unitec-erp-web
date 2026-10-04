@php
    $searchFields = [
        'codigo' => 'CÓDIGO',
        'nome_razao' => 'RAZÃO/NOME',
        'apelido_fantasia' => 'FANTASIA/APELIDO',
        'cpf_cnpj' => 'CPF/CNPJ',
        'rg_ie' => 'RG/IE',
        'endereco' => 'ENDEREÇO',
    ];

    $pageSizeOptions = [25, 50, 100];
    $activeSearchFields = $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn];
    $searchButtonLabel = implode(' + ', array_map(
        fn (string $key): string => $searchFields[$key] ?? $key,
        $activeSearchFields,
    ));
    $uppercaseSearch = array_intersect($activeSearchFields, ['nome_razao', 'apelido_fantasia', 'endereco']) !== [];
    $numericSearch = $activeSearchFields !== [] && array_diff($activeSearchFields, ['codigo']) === [];
@endphp

<div
    class="erp-pessoas"
    wire:ignore.self
    @if ($this->erpListSyncPollEnabled())
        wire:poll.{{ $this->erpListSyncPollIntervalSeconds() }}s.visible="pollErpListSync"
    @endif
>
    <div class="erp-pessoas__filters">
        <div class="erp-pessoas__filters-row">
            <div class="erp-pessoas__search-group">
                <span class="erp-pessoas__locate-label">F6 | Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                    'markedFields' => $activeSearchFields,
                    'buttonLabel' => $searchButtonLabel,
                    'closeOnSelect' => false,
                ])
                <input
                    type="text"
                    wire:model.live.debounce.350ms="localSearch"
                    wire:key="pessoas-local-search-{{ implode('-', $activeSearchFields) }}-{{ $this->tipoFilter }}"
                    class="erp-pessoas__input erp-pessoas__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($uppercaseSearch) data-erp-uppercase @endif
                    @if ($numericSearch) inputmode="numeric" @endif
                >
            </div>

            <div class="erp-pessoas__page-size-group">
                <label class="erp-pessoas__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-pessoas__select erp-pessoas__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.pessoas.tabs')

    <p class="erp-pessoas__hint">
        Clique na tecla [DELETE] para excluir pessoa.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
