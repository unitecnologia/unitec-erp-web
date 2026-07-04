<div class="erp-empresas" wire:ignore.self>
    <div class="erp-empresas__locate">
        <span class="erp-empresas__locate-label">F5 | Localizar &lt;&lt;Código&gt;&gt;</span>
        <div class="erp-empresas__locate-controls">
            <input
                id="erp-empresas-search"
                type="text"
                wire:model.live.debounce.300ms="localSearch"
                class="erp-empresas__input"
                autocomplete="off"
            >
        </div>
    </div>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])
</div>

<script data-navigate-track>
    if (! window.__erpEmpresaFocusBound) {
        window.__erpEmpresaFocusBound = true;

        window.Livewire.on('erp-empresa-focus-search', () => {
            document.getElementById('erp-empresas-search')?.focus();
            document.getElementById('erp-empresas-search')?.select?.();
        });
    }
</script>
