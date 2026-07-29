@php
    use App\Models\Venda;
    use App\Support\Erp\ErpTimezone;

    $searchFields = [
        'numero' => 'NÚMERO',
        'data' => 'DATA',
        'cliente' => 'CLIENTE',
        'vendedor' => 'VENDEDOR',
        'plataforma' => 'PLATAFORMA',
        'meio_pagamento' => 'MEIO DE PAGAMENTO',
        'total' => 'TOTAL',
        'situacao' => 'SITUAÇÃO',
        'tipo' => 'TIPO',
        'hora' => 'HORA',
    ];

    $isDateSearch = $this->searchColumn === 'data';
    $isTimeSearch = $this->searchColumn === 'hora';
    $isMeioPagamentoSearch = $this->searchColumn === 'meio_pagamento';
    $isPlataformaSearch = $this->searchColumn === 'plataforma';
    $isSituacaoSearch = $this->searchColumn === 'situacao';
    $isTipoSearch = $this->searchColumn === 'tipo';

    $hojeIso = ErpTimezone::toLocal()->toDateString();
    $localSearchDeIso = filled($this->localSearchDe) ? $this->localSearchDe : $hojeIso;
    $localSearchAteIso = filled($this->localSearchAte) ? $this->localSearchAte : $hojeIso;
@endphp

<div class="erp-vendas__search-group">
    <span class="erp-vendas__locate-label">F8 | Localizar</span>
    @include('filament.components.erp.shared.search-field-dropdown', [
        'fields' => $searchFields,
        'searchColumn' => $this->searchColumn,
    ])

    @if ($isDateSearch)
        <div
            class="erp-vendas__search-date-range"
            data-erp-date-group
            wire:key="vendas-local-search-dates"
        >
            <label class="erp-vendas__period-label">
                de
                <input
                    type="date"
                    data-erp-date
                    data-wire-field="localSearchDe"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $localSearchDeIso }}"
                    value="{{ $localSearchDeIso }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    class="erp-vendas__period-input erp-vendas__search-date-from"
                >
            </label>
            <label class="erp-vendas__period-label">
                até
                <input
                    type="date"
                    data-erp-date
                    data-wire-field="localSearchAte"
                    data-erp-date-wire="iso"
                    data-erp-date-initial="{{ $localSearchAteIso }}"
                    value="{{ $localSearchAteIso }}"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="dd/mm/aaaa"
                    class="erp-vendas__period-input erp-vendas__search-date-to"
                >
            </label>
        </div>
    @elseif ($isTimeSearch)
        <div class="erp-vendas__search-time-range" wire:key="vendas-local-search-times">
            <label class="erp-vendas__period-label">
                hora inicial
                <input
                    type="time"
                    wire:model="localSearchHoraDe"
                    class="erp-vendas__period-input erp-vendas__search-time-from"
                >
            </label>
            <label class="erp-vendas__period-label">
                hora fim
                <input
                    type="time"
                    wire:model="localSearchHoraAte"
                    class="erp-vendas__period-input erp-vendas__search-time-to"
                >
            </label>
        </div>
    @elseif ($isPlataformaSearch)
        <select
            wire:model="localSearch"
            wire:key="vendas-local-search-plataforma"
            class="erp-vendas__select erp-vendas__search-value-select"
        >
            <option value="">TODAS</option>
            @foreach (Venda::plataformaLabels() as $value => $label)
                <option value="{{ $value }}">{{ mb_strtoupper($label, 'UTF-8') }}</option>
            @endforeach
        </select>
    @elseif ($isMeioPagamentoSearch)
        <select
            wire:model="localSearch"
            wire:key="vendas-local-search-meio_pagamento"
            class="erp-vendas__select erp-vendas__search-value-select"
        >
            <option value="">TODOS</option>
            @foreach ($this->meioPagamentoFilterOptions as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    @elseif ($isSituacaoSearch)
        <select
            wire:model="localSearch"
            wire:key="vendas-local-search-situacao"
            class="erp-vendas__select erp-vendas__search-value-select"
        >
            <option value="">TODOS</option>
            @foreach (Venda::statusLabels() as $value => $label)
                <option value="{{ $value }}">{{ mb_strtoupper($label, 'UTF-8') }}</option>
            @endforeach
        </select>
    @elseif ($isTipoSearch)
        <select
            wire:model="localSearch"
            wire:key="vendas-local-search-tipo"
            class="erp-vendas__select erp-vendas__search-value-select"
        >
            <option value="">TODOS</option>
            @foreach (Venda::tipoLabels() as $value => $label)
                <option value="{{ $value }}">{{ mb_strtoupper($label, 'UTF-8') }}</option>
            @endforeach
        </select>
    @else
        <input
            type="text"
            wire:model="localSearch"
            wire:keydown.enter="search"
            wire:key="vendas-local-search-{{ $this->searchColumn }}"
            class="erp-vendas__input erp-vendas__search-text"
            placeholder="Digite para pesquisar"
            autocomplete="off"
            @if (in_array($this->searchColumn, ['cliente', 'vendedor', 'meio_pagamento'], true)) data-erp-uppercase @endif
        >
    @endif
</div>
