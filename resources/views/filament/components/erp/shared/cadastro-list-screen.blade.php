{{--
    Toolbar de listagem no padrão Produtos (F6 + dropdown + Pesquisa/Limpar + por página).

    @var string $pageClass              ex.: erp-unidades
    @var array<string,string> $searchFields
    @var string|null $hint
    @var string|null $searchInputClass  seletor CSS do input (default: .{pageClass}__input)
    @var bool $showFieldDropdown        default true; false = só input
    @var string|null $wireKeyPrefix
    @var string|null $uppercaseColumns  CSV de colunas que forçam uppercase no input
    @var string|null $extraClass        classes extras no wrapper
    @var string|null $beforeFiltersView view parcial acima da toolbar
--}}
@php
    $pageClass = $pageClass ?? 'erp-unidades';
    $searchFields = $searchFields ?? ['codigo' => 'CÓDIGO'];
    $pageSizeOptions = [25, 50, 100];
    $showFieldDropdown = $showFieldDropdown ?? (count($searchFields) > 1);
    $wireKeyPrefix = $wireKeyPrefix ?? $pageClass;
    $uppercaseColumns = collect(explode(',', (string) ($uppercaseColumns ?? 'nome,descricao,sigla,fantasia,razao_social,q')))
        ->map(fn ($v) => trim($v))
        ->filter()
        ->all();
    // Sempre força caixa alta no Localizar (cadastros).
    $forceSearchUppercase = $forceSearchUppercase ?? true;
    $extraClass = trim((string) ($extraClass ?? ''));
    $hint = $hint ?? null;
    $beforeFiltersView = $beforeFiltersView ?? null;
    $searchColumn = $this->searchColumn ?? array_key_first($searchFields) ?? 'q';
@endphp

<div class="{{ $pageClass }} {{ $extraClass }}" wire:ignore.self>
    @if (filled($beforeFiltersView))
        @include($beforeFiltersView)
    @endif

    <div class="{{ $pageClass }}__filters">
        <div class="{{ $pageClass }}__filters-row">
            <div class="{{ $pageClass }}__search-group">
                <span class="{{ $pageClass }}__locate-label">F6 | Localizar</span>
                @if ($showFieldDropdown)
                    @include('filament.components.erp.shared.search-field-dropdown', [
                        'fields' => $searchFields,
                        'searchColumn' => $searchColumn,
                        'wireProperty' => 'searchColumn',
                    ])
                @endif
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    wire:key="{{ $wireKeyPrefix }}-local-search-{{ $searchColumn }}"
                    class="{{ $pageClass }}__input {{ $pageClass }}__search-text"
                    placeholder="Digite para pesquisar"
                    autocomplete="off"
                    @if ($forceSearchUppercase || in_array($searchColumn, $uppercaseColumns, true)) data-erp-uppercase @endif
                    @if ($searchColumn === 'codigo') inputmode="numeric" @endif
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

    @if (filled($hint))
        <p class="{{ $pageClass }}__hint">{{ $hint }}</p>
    @endif

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
