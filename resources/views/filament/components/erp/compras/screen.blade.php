<div class="erp-compras" wire:ignore.self>
    @php
        $pageSizeOptions = [25, 50, 100];
    @endphp

    <div class="erp-compras__filters">
        <div class="erp-compras__filters-row">
            <div class="erp-compras__empresa-group">
                <span class="erp-compras__empresa-label">Empresa:</span>
                <span class="erp-compras__empresa-value">{{ $this->empresaNome }}</span>
            </div>

            @include('filament.components.erp.compras.toolbar-filters')

            <div class="erp-compras__page-size-group">
                <label class="erp-compras__page-size-label">
                    POR PÁGINA
                    <select wire:model.live="tableRecordsPerPage" class="erp-compras__select erp-compras__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.compras.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
