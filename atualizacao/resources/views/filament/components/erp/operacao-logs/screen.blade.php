@php
    $operacoes = [
        'todas' => 'Todas',
        'ESTORNAR_VENDA' => 'Estornar venda',
    ];

    $resultados = [
        'todos' => 'Todos',
        'ok' => 'OK',
        'erro' => 'Erro',
    ];
@endphp

<div class="erp-operacao-logs" wire:ignore.self>
    <div class="erp-operacao-logs__filters">
        <div class="erp-operacao-logs__row">
            <div class="erp-operacao-logs__period">
                <span class="erp-operacao-logs__label">Período</span>
                <label class="erp-operacao-logs__field">
                    de
                    <input
                        type="date"
                        data-wire-field="localSearchDe"
                        data-erp-date-wire="iso"
                        class="erp-operacao-logs__input"
                    >
                </label>
                <label class="erp-operacao-logs__field">
                    até
                    <input
                        type="date"
                        data-wire-field="localSearchAte"
                        data-erp-date-wire="iso"
                        class="erp-operacao-logs__input"
                    >
                </label>
            </div>

            <label class="erp-operacao-logs__field">
                Operação
                <select wire:model.live="operacaoFilter" class="erp-operacao-logs__select">
                    @foreach ($operacoes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-operacao-logs__field">
                Resultado
                <select wire:model.live="resultadoFilter" class="erp-operacao-logs__select">
                    @foreach ($resultados as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="erp-operacao-logs__search">
                <span class="erp-operacao-logs__label">F8 | Localizar</span>
                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="search"
                    class="erp-operacao-logs__input erp-operacao-logs__search-input"
                    placeholder="Resumo, documento, usuário..."
                    autocomplete="off"
                >
            </div>

            <div class="erp-operacao-logs__actions">
                <button
                    type="button"
                    wire:click="search"
                    onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-operacao-logs') ?? document)"
                    class="erp-operacao-logs__btn"
                >
                    Pesquisa
                </button>
                <button type="button" wire:click="clearSearch" class="erp-operacao-logs__btn erp-operacao-logs__btn--secondary">
                    Limpar
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
