@php
    $orderFields = [
        'nome_razao' => 'Nome',
        'codigo' => 'Código',
        'apelido_fantasia' => 'Apelido',
    ];
@endphp

<div class="erp-aniversariantes" wire:ignore.self>
    <div class="erp-aniversariantes__toolbar">
        <div class="erp-aniversariantes__panel">
            <span class="erp-aniversariantes__panel-title">Filtrar</span>

            <label class="erp-aniversariantes__period-check">
                <input type="checkbox" wire:model.live="informarPeriodo">
                Informar Período
            </label>

            <div class="erp-aniversariantes__period">
                <label class="erp-aniversariantes__period-label">
                    de
                    <input
                        type="date"
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        class="erp-aniversariantes__period-input erp-aniversariantes__period-from"
                        @disabled(! $this->informarPeriodo)
                    >
                </label>
                <label class="erp-aniversariantes__period-label">
                    até
                    <input
                        type="date"
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        class="erp-aniversariantes__period-input"
                        @disabled(! $this->informarPeriodo)
                    >
                </label>
                <button
                    type="button"
                    wire:click="applyPeriodFilter"
                    onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-aniversariantes') ?? document)"
                    class="erp-aniversariantes__btn"
                    @disabled(! $this->informarPeriodo)
                >
                    Filtrar Período
                </button>
            </div>
        </div>

        <div class="erp-aniversariantes__panel">
            <span class="erp-aniversariantes__panel-title">Ordenar por</span>
            <select wire:model.live="orderColumn" class="erp-aniversariantes__select">
                @foreach ($orderFields as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>
