@php
    $statusOptions = [
        'pendentes' => 'Pendentes',
        'expedidos' => 'Expedidos',
        'todos' => 'Todos',
    ];
@endphp

<div class="erp-expedicao-root">
<div class="erp-nfe erp-expedicao" wire:ignore.self>

    <div class="erp-expedicao__topbar">
        <span class="erp-expedicao__topbar-title">Controle de Expedição</span>
    </div>

    <fieldset class="erp-expedicao__consulta">
        <legend>Campos para Consulta</legend>
        <div class="erp-expedicao__consulta-row">
            <div class="erp-expedicao__inputs">
                <div class="erp-expedicao__field erp-expedicao__field--periodo">
                    <span>Período de</span>
                    <div
                        class="erp-expedicao__periodo"
                        data-erp-date-group
                    >
                        <input
                            type="date"
                            data-wire-field="periodoDe"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoDe }}"
                            class="erp-nfe__period-input erp-expedicao__period-from"
                            aria-label="Período inicial"
                        >
                        <span class="erp-expedicao__periodo-sep" aria-hidden="true">até</span>
                        <input
                            type="date"
                            data-wire-field="periodoAte"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoAte }}"
                            class="erp-nfe__period-input"
                            aria-label="Período final"
                        >
                    </div>
                </div>

                <label class="erp-expedicao__field erp-expedicao__field--status">
                    <span>Status</span>
                    <select wire:model.live="statusFilter" class="erp-nfe__select">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-expedicao__field erp-expedicao__field--pedido">
                    <span>Nº pedido</span>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="numeroPedido"
                        wire:keydown.enter="consultar"
                        onkeydown="if (event.key === 'Enter') window.ErpDatepicker?.commitAllIn(this.closest('.erp-expedicao') ?? document)"
                        class="erp-nfe__input erp-expedicao__pedido-input"
                        placeholder="Digite o nº"
                    >
                </label>

                <div class="erp-expedicao__field erp-expedicao__field--action">
                    <span class="erp-expedicao__field-spacer" aria-hidden="true">&nbsp;</span>
                    <button
                        type="button"
                        wire:click="consultar"
                        onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-expedicao') ?? document)"
                        class="erp-expedicao__btn-consultar"
                    >
                        Consultar
                    </button>
                </div>
            </div>
        </div>
    </fieldset>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
    @include('filament.components.erp.form-scripts')
</div>
</div>

@include('filament.components.erp.expedicao.limite-pedidos-aviso')
