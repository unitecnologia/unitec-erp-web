@php
    $statusOptions = [
        'todos' => 'Todos',
        'aberta' => 'Aberta',
        'fechada' => 'Fechada',
        'cancelada' => 'Cancelada',
    ];
@endphp

<div class="erp-cargas-root">
<div class="erp-nfe erp-cargas" wire:ignore.self>

    <div class="erp-cargas__topbar">
        <span class="erp-cargas__topbar-title">Carga / Romaneio</span>
    </div>

    <fieldset class="erp-cargas__consulta">
        <legend>Campos para Consulta</legend>
        <div class="erp-cargas__consulta-row">
            <div class="erp-cargas__inputs">
                <div class="erp-cargas__field erp-cargas__field--periodo">
                    <span class="erp-cargas__label">Período de</span>
                    <div class="erp-cargas__periodo" data-erp-date-group>
                        <input
                            type="date"
                            data-wire-field="periodoDe"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoDe }}"
                            class="erp-nfe__period-input erp-cargas__period-from"
                            aria-label="Período inicial"
                        >
                        <span class="erp-cargas__periodo-sep" aria-hidden="true">até</span>
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

                <label class="erp-cargas__field erp-cargas__field--status">
                    <span class="erp-cargas__label">Status</span>
                    <select wire:model="statusFilter" class="erp-nfe__select erp-cargas__select">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-cargas__field erp-cargas__field--numero">
                    <span class="erp-cargas__label">Nº carga</span>
                    <input
                        type="text"
                        wire:model="numeroCarga"
                        wire:keydown.enter="consultar"
                        onkeydown="if (event.key === 'Enter') window.ErpDatepicker?.commitAllIn(this.closest('.erp-cargas') ?? document)"
                        class="erp-nfe__input erp-cargas__numero-input"
                        placeholder="Digite o nº"
                        inputmode="numeric"
                        autocomplete="off"
                    >
                </label>

                <button
                    type="button"
                    wire:click="consultar"
                    onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-cargas') ?? document)"
                    class="erp-cargas__btn-consultar"
                >
                    Consultar
                </button>
            </div>
        </div>
    </fieldset>
</div>
</div>
