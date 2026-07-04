@php
    use Illuminate\Support\Carbon;

    $searchFields = [
        'cliente' => 'CLIENTE',
        'numero' => 'NÚMERO',
        'vendedor' => 'VENDEDOR',
        'cidade' => 'CIDADE',
        'uf' => 'UF',
    ];

    $pageSizeOptions = [25, 50, 100];
    $periodoDeValor = filled($this->periodoDe)
        ? Carbon::parse($this->periodoDe)->format('d/m/Y')
        : '';
    $periodoAteValor = filled($this->periodoAte)
        ? Carbon::parse($this->periodoAte)->format('d/m/Y')
        : '';
@endphp

<div class="erp-orcamentos">
    <div class="erp-orcamentos__filters">
        <div class="erp-orcamentos__filters-row">
            <div class="erp-orcamentos__search-group">
                <span class="erp-orcamentos__locate-label">Localizar</span>
                <select wire:model.live="searchColumn" class="erp-orcamentos__select erp-orcamentos__search-field">
                    @foreach ($searchFields as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <span class="erp-orcamentos__search-desc-label">Descrição</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="localSearch"
                    wire:key="orcamentos-local-search-{{ $this->searchColumn }}"
                    class="erp-orcamentos__input erp-orcamentos__search-text"
                    placeholder="Digite para pesquisar"
                >
            </div>

            <div
                class="erp-orcamentos__period-group"
                wire:ignore.self
                data-erp-date-group
                data-erp-date-apply-method="applyPeriodFilter"
            >
                <span class="erp-orcamentos__filter-title">Filtro</span>
                <label class="erp-orcamentos__period-label">
                    Período de
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $this->periodoDe }}"
                        value="{{ $periodoDeValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-orcamentos__period-input erp-orcamentos__period-from erp-date-input"
                    >
                </label>
                <label class="erp-orcamentos__period-label">
                    até
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $this->periodoAte }}"
                        value="{{ $periodoAteValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-orcamentos__period-input erp-date-input"
                    >
                </label>
                <button
                    type="button"
                    onclick="window.ErpDatepicker?.applyPeriodGroupFromButton(this)"
                    class="erp-orcamentos__btn"
                >
                    Filtrar Período
                </button>
            </div>

            <div class="erp-orcamentos__page-size-group">
                <label class="erp-orcamentos__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-orcamentos__select erp-orcamentos__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.orcamentos.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')

    @php
        use App\Support\Erp\ErpAssetVersion;
    @endphp
</div>
