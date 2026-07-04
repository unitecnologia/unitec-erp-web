@php
    use App\Models\Venda;

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
@endphp

<div class="erp-vendas__locate">
    <span class="erp-vendas__locate-label">Localizar</span>
    <div class="erp-vendas__locate-controls">
        <select wire:model.live="searchColumn" class="erp-vendas__select erp-vendas__search-field">
            @foreach ($searchFields as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        @if ($isDateSearch)
            <div class="erp-vendas__search-date-range" wire:key="vendas-local-search-dates">
                <label class="erp-vendas__period-label">
                    de
                    <input
                        type="date"
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        class="erp-vendas__period-input erp-vendas__search-date-from"
                    >
                </label>
                <label class="erp-vendas__period-label">
                    até
                    <input
                        type="date"
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
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
                        wire:model.live="localSearchHoraDe"
                        class="erp-vendas__period-input erp-vendas__search-time-from"
                    >
                </label>
                <label class="erp-vendas__period-label">
                    hora fim
                    <input
                        type="time"
                        wire:model.live="localSearchHoraAte"
                        class="erp-vendas__period-input erp-vendas__search-time-to"
                    >
                </label>
            </div>
        @elseif ($isPlataformaSearch)
            <select
                wire:model.live="localSearch"
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
                wire:model.live="localSearch"
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
                wire:model.live="localSearch"
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
                wire:model.live="localSearch"
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
                wire:model.live="localSearch"
                wire:keydown.enter="search"
                wire:key="vendas-local-search-{{ $this->searchColumn }}"
                class="erp-vendas__input erp-vendas__search-text"
                placeholder="Digite para pesquisar"
                autocomplete="off"
                @if (in_array($this->searchColumn, ['cliente', 'vendedor'], true)) data-erp-uppercase @endif
            >
        @endif
    </div>
</div>
