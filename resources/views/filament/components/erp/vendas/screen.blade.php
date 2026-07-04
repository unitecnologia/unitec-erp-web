<div class="erp-vendas" wire:ignore.self>
    @php
        $pageSizeOptions = [25, 50, 100];
    @endphp

    <div class="erp-vendas__filters">
        <div class="erp-vendas__filters-row">
            <div class="erp-vendas__empresa-group">
                <span class="erp-vendas__empresa-label">Empresa:</span>
                <span class="erp-vendas__empresa-value">{{ $this->empresaNome }}</span>
            </div>

            @include('filament.components.erp.vendas.toolbar-filters')

            <div class="erp-vendas__page-size-group">
                <label class="erp-vendas__page-size-label">
                    POR PÁGINA
                    <select wire:model.live="tableRecordsPerPage" class="erp-vendas__select erp-vendas__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.vendas.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
