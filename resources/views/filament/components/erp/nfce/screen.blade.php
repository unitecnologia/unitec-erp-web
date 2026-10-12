@php
    $pageSizeOptions = [25, 50, 100];
    $searchFields = array_map(
        fn (string $label): string => mb_strtoupper($label, 'UTF-8'),
        $this->searchColumnLabels,
    );
    $searchColumn = array_key_exists($this->searchColumn, $searchFields) ? $this->searchColumn : 'serie';
    $placeholders = [
        'serie' => 'Digite a série',
        'numero' => 'Digite o número',
        'chave' => 'Digite a chave',
        'protocolo' => 'Digite o protocolo',
        'cpf' => 'Digite o CPF/CNPJ',
        'caixa' => 'Digite o caixa',
        'usuario' => 'Digite o usuário',
        'vendedor' => 'Digite o vendedor',
        'pedido' => 'Digite o nº do pedido',
    ];
    $numericSearch = in_array($searchColumn, ['serie', 'numero', 'chave', 'protocolo', 'cpf', 'pedido'], true);
@endphp

<div class="erp-nfe" wire:ignore.self>
    @include('filament.components.erp.nfce.grid-columns-style')

    <div class="erp-nfe__filters">
        <div class="erp-nfe__filters-row">
            @include('filament.components.erp.empresa-badge', [
                'nome' => $this->empresaNome,
                'prefix' => 'erp-nfe',
            ])

            <div class="erp-nfe__locate">
                <span class="erp-nfe__locate-label">Localizar</span>
                <div class="erp-nfe__locate-controls">
                    @include('filament.components.erp.shared.search-field-dropdown', [
                        'fields' => $searchFields,
                        'searchColumn' => $searchColumn,
                        'wireProperty' => 'searchColumn',
                    ])
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="localSearch"
                        wire:keydown.enter="applyFooterSearch"
                        wire:key="nfce-local-search-{{ $searchColumn }}"
                        class="erp-nfe__input erp-nfe__search-text"
                        placeholder="{{ $placeholders[$searchColumn] ?? 'Digite para pesquisar' }}"
                        autocomplete="off"
                        @if ($numericSearch) inputmode="numeric" @endif
                    >
                </div>
            </div>

            <div class="erp-nfe__filters-main">
                <div
                    class="erp-nfe__period-group"
                    wire:ignore
                    data-erp-date-group
                    data-erp-date-auto-apply="1"
                    data-erp-date-apply-method="applyPeriodFilterAuto"
                >
                    <span class="erp-nfe__footer-label">Período</span>
                    <label class="erp-nfe__period-label">
                        <span class="erp-nfe__period-caption">de</span>
                        <input
                            type="date"
                            data-erp-date
                            data-wire-field="periodoDe"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoDe }}"
                            value="{{ $this->periodoDe }}"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="dd/mm/aaaa"
                            class="erp-nfe__period-input erp-nfe__period-from"
                        >
                    </label>
                    <label class="erp-nfe__period-label">
                        <span class="erp-nfe__period-caption">até</span>
                        <input
                            type="date"
                            data-erp-date
                            data-wire-field="periodoAte"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoAte }}"
                            value="{{ $this->periodoAte }}"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="dd/mm/aaaa"
                            class="erp-nfe__period-input"
                        >
                    </label>
                </div>

                <div class="erp-nfe__chave-group erp-nfe__chave-group--inline">
                    <label class="erp-nfe__chave-label">
                        <span class="erp-nfe__footer-label">Chave NFC-e</span>
                        <input
                            type="text"
                            value="{{ $this->chaveFilterApplied }}"
                            x-data="{ applied: @js($this->chaveFilterApplied) }"
                            x-on:input.debounce.250ms="
                                const digits = $el.value.replace(/\D/g, '').slice(0, 44);
                                if ($el.value !== digits) $el.value = digits;
                                if ((digits.length === 44 || digits === '') && digits !== applied) {
                                    applied = digits;
                                    $wire.applyChaveFilterValue(digits);
                                }
                            "
                            x-on:keydown.enter.prevent="
                                const digits = $el.value.replace(/\D/g, '').slice(0, 44);
                                if (digits !== applied) {
                                    applied = digits;
                                    $wire.applyChaveFilterValue(digits);
                                }
                            "
                            class="erp-nfe__input erp-nfe__chave-input"
                            placeholder="44 dígitos"
                            inputmode="numeric"
                            maxlength="60"
                            autocomplete="off"
                        >
                    </label>
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

    @include('filament.components.erp.nfce.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
