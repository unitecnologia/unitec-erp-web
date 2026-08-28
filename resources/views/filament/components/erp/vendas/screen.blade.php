<div
    class="erp-vendas"
    wire:ignore.self
    @if ($this->erpListSyncPollEnabled())
        wire:poll.{{ $this->erpListSyncPollIntervalSeconds() }}s.visible="pollErpListSync"
    @endif
>
    @php
        $pageSizeOptions = [25, 50, 100];
    @endphp

    <div class="erp-vendas__filters">
        <div class="erp-vendas__filters-row">
            @include('filament.components.erp.empresa-badge', [
                'nome' => $this->empresaNome,
                'prefix' => 'erp-vendas',
            ])

            @include('filament.components.erp.vendas.toolbar-filters')

            <div class="erp-vendas__search-actions">
                <button
                    type="button"
                    wire:click="search"
                    onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-vendas') ?? document)"
                    class="erp-vendas__btn"
                >Pesquisa</button>
                <button type="button" wire:click="clearSearch" class="erp-vendas__btn erp-vendas__btn--secondary">Limpar</button>
            </div>

            <div class="erp-vendas__page-size-group">
                <label class="erp-vendas__page-size-label">
                    por página
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

    <p class="erp-vendas__hint">
        Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.
    </p>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
