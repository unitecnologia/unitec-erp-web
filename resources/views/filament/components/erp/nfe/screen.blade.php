@php
    $pageSizeOptions = [25, 50, 100];
@endphp

<div class="erp-nfe" wire:ignore.self>
    <div class="erp-nfe__filters">
        <div class="erp-nfe__filters-row">
            <div class="erp-nfe__empresa-group">
                <span class="erp-nfe__empresa-label">Empresa:</span>
                <span class="erp-nfe__empresa-value">{{ $this->empresaNome }}</span>
            </div>

            <label class="erp-nfe__client-label">
                Selecione Cliente
                <select wire:model.live="clienteFilter" class="erp-nfe__select erp-nfe__client-select">
                    <option value="todos">&lt;todos os clientes&gt;</option>
                    @foreach ($this->clientesOptions as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </label>

            <div class="erp-nfe__filters-main">
                <div class="erp-nfe__period-group">
                    <span class="erp-nfe__footer-label">Período</span>
                    <label class="erp-nfe__period-label">
                        de
                        <input
                            type="date"
                            data-wire-field="periodoDe"
                            data-erp-date-wire="iso"
                            class="erp-nfe__period-input erp-nfe__period-from"
                        >
                    </label>
                    <label class="erp-nfe__period-label">
                        até
                        <input
                            type="date"
                            data-wire-field="periodoAte"
                            data-erp-date-wire="iso"
                            class="erp-nfe__period-input"
                        >
                    </label>
                    <button
                        type="button"
                        wire:click="applyPeriodFilter"
                        onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-nfe') ?? document)"
                        class="erp-nfe__btn erp-nfe__btn--filter"
                    >
                        Filtrar
                    </button>
                </div>

                <input
                    type="text"
                    wire:model="localSearch"
                    wire:keydown.enter="applyFooterSearch"
                    wire:key="nfe-local-search-{{ $this->searchColumn }}"
                    class="erp-nfe__input erp-nfe__search-text"
                    placeholder="Pesquisa"
                    autocomplete="off"
                >

                <div class="erp-nfe__chave-group">
                    <label class="erp-nfe__chave-label">
                        CHAVE NF-e
                        <input
                            type="text"
                            wire:model="chaveFilter"
                            wire:keydown.enter="applyChaveFilter"
                            class="erp-nfe__input erp-nfe__chave-input"
                            inputmode="numeric"
                            maxlength="44"
                            autocomplete="off"
                        >
                    </label>
                    <button type="button" wire:click="applyChaveFilter" class="erp-nfe__btn">OK</button>
                </div>
            </div>

            <div class="erp-nfe__page-size-group">
                <label class="erp-nfe__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-nfe__select erp-nfe__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.nfe.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
