<div class="erp-compras" wire:ignore.self>
    @php
        $pageSizeOptions = [25, 50, 100];
    @endphp

    <div class="erp-compras__filters">
        <div class="erp-compras__filters-row">
            @include('filament.components.erp.empresa-badge', [
                'nome' => $this->empresaNome,
                'prefix' => 'erp-compras',
            ])

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

    <script>
        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-nf-forn-overlay-close') {
                return;
            }

            const root = document.querySelector('.erp-compras-page');
            const componentEl = root?.closest('[wire\\:id]');
            const component = componentEl && window.Livewire
                ? window.Livewire.find(componentEl.getAttribute('wire:id'))
                : null;

            if (! component) {
                return;
            }

            const produtoId = Number.parseInt(String(event.data.produtoId ?? ''), 10);
            const itemIndex = Number.parseInt(String(event.data.itemIndex ?? ''), 10);

            if (! Number.isNaN(produtoId) && produtoId > 0 && ! Number.isNaN(itemIndex) && itemIndex >= 0) {
                component.call(
                    'applyOverlayProdutoXmlSaved',
                    itemIndex,
                    produtoId,
                    String(event.data.produtoCodigo ?? ''),
                    String(event.data.produtoDescricao ?? ''),
                    String(event.data.produtoGrupo ?? ''),
                    String(event.data.produtoPrecoVenda ?? ''),
                );

                return;
            }

            component.call('closeProductOverlay');
        });
    </script>
</div>
