@php
    use Illuminate\Support\Carbon;

    $isPeriodoEntrada = $this->searchColumn === 'periodo_entrada';
    $isDateSearch = $this->searchColumn === 'data_emissao';

    $periodoDeIso = filled($this->periodoDe) ? $this->periodoDe : '';
    $periodoAteIso = filled($this->periodoAte) ? $this->periodoAte : '';
    $periodoDeValor = filled($periodoDeIso) ? Carbon::parse($periodoDeIso)->format('d/m/Y') : '';
    $periodoAteValor = filled($periodoAteIso) ? Carbon::parse($periodoAteIso)->format('d/m/Y') : '';

    $emissaoDeIso = filled($this->localSearchDe) ? $this->localSearchDe : '';
    $emissaoAteIso = filled($this->localSearchAte) ? $this->localSearchAte : '';
    $emissaoDeValor = filled($emissaoDeIso) ? Carbon::parse($emissaoDeIso)->format('d/m/Y') : '';
    $emissaoAteValor = filled($emissaoAteIso) ? Carbon::parse($emissaoAteIso)->format('d/m/Y') : '';
@endphp

<div class="erp-nfe__locate erp-nfe__filtro-unificado">
    <span class="erp-nfe__locate-label"><kbd>F12</kbd> Filtro</span>
    <div class="erp-nfe__locate-controls">
        <select wire:model.live="searchColumn" class="erp-nfe__select erp-nfe__search-field">
            @foreach ($filterFields as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        @if ($isPeriodoEntrada)
            <div
                class="erp-nfe__search-date-range"
                wire:key="nf-forn-filter-periodo-entrada"
                wire:ignore.self
                data-erp-date-group
            >
                <label class="erp-nfe__period-label">
                    de
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoDeIso }}"
                        value="{{ $periodoDeValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-nfe__period-from erp-date-input"
                    >
                </label>
                <label class="erp-nfe__period-label">
                    até
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoAteIso }}"
                        value="{{ $periodoAteValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-nfe__period-to erp-date-input"
                    >
                </label>
            </div>
        @elseif ($isDateSearch)
            <div
                class="erp-nfe__search-date-range"
                wire:key="nf-forn-filter-data-emissao"
                wire:ignore.self
                data-erp-date-group
            >
                <label class="erp-nfe__period-label">
                    de
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $emissaoDeIso }}"
                        value="{{ $emissaoDeValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-nfe__search-date-from erp-date-input"
                    >
                </label>
                <label class="erp-nfe__period-label">
                    até
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $emissaoAteIso }}"
                        value="{{ $emissaoAteValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-nfe__search-date-to erp-date-input"
                    >
                </label>
            </div>
        @else
            <input
                type="text"
                wire:model.live="localSearch"
                wire:keydown.enter="applyFilter"
                wire:key="nf-forn-filter-text-{{ $this->searchColumn }}"
                class="erp-nfe__input erp-nfe__search-text"
                placeholder="DIGITE AQUI SUA PESQUISA"
                autocomplete="off"
                @if ($this->searchColumn === 'nome') data-erp-uppercase @endif
                @if (in_array($this->searchColumn, ['chave', 'cnpj'], true)) inputmode="numeric" @endif
                @if ($this->searchColumn === 'chave') maxlength="44" @endif
            >
        @endif

        <button
            type="button"
            wire:click="applyFilter"
            onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-nfe') ?? document)"
            class="erp-nfe__btn erp-nfe__btn--filter"
        >
            Filtrar
        </button>
    </div>
</div>
