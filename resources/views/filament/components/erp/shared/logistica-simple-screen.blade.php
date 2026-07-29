@php
    $searchFields = $searchFields ?? ['codigo' => 'CÓDIGO', 'nome' => $nomeSearchLabel ?? 'NOME'];
    $pageClass = $pageClass ?? 'erp-tomadores-servico';
    $pageSizeOptions = [25, 50, 100];
    $deleteHint = $deleteHint ?? 'registro';
@endphp

<div class="{{ $pageClass }}" wire:ignore.self>
    <div class="{{ $pageClass }}__filters">
        <div class="{{ $pageClass }}__filters-row">
            <div class="{{ $pageClass }}__search-group">
                <span class="{{ $pageClass }}__locate-label">F6 | Localizar</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => $searchFields,
                    'searchColumn' => $this->searchColumn,
                ])
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="{{ $pageClass }}-local-search-{{ $this->searchColumn }}"
                    class="{{ $pageClass }}__input {{ $pageClass }}__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($this->searchColumn === 'nome') data-erp-uppercase @endif
                    @if ($this->searchColumn === 'codigo') inputmode="numeric" @endif
                >
            </div>

            <div class="{{ $pageClass }}__search-actions">
                <button type="button" wire:click="search" class="{{ $pageClass }}__btn">Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="{{ $pageClass }}__btn {{ $pageClass }}__btn--secondary">Limpar</button>
            </div>

            <div class="{{ $pageClass }}__page-size-group">
                <label class="{{ $pageClass }}__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="{{ $pageClass }}__select {{ $pageClass }}__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="{{ $pageClass }}__hint">
        Clique na tecla [DELETE] para excluir {{ $deleteHint }}.
    </p>

    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
