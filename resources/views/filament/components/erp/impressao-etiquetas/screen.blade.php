<div class="erp-impressao-etiquetas" wire:ignore.self>
    <div class="erp-impressao-etiquetas__layout">
        <div class="erp-impressao-etiquetas__main">
            <div class="erp-impressao-etiquetas__locate">
                <span class="erp-impressao-etiquetas__locate-label">F5 | Localizar</span>
                <input type="text" wire:model.live.debounce.300ms="localSearch" class="erp-impressao-etiquetas__input">
            </div>
        </div>
        <div class="erp-impressao-etiquetas__sidebar">
            <button type="button" wire:click="pesquisar" class="erp-impressao-etiquetas__side-btn" data-erp-key="F2"><span>🔍</span><span><kbd>F2</kbd> | Pesquisar</span></button>
            <button type="button" wire:click="limparBusca" class="erp-impressao-etiquetas__side-btn" data-erp-key="F3"><span>✕</span><span><kbd>F3</kbd> | Limpar</span></button>
            <button type="button" wire:click="modulePending('Imprimir')" class="erp-impressao-etiquetas__side-btn" data-erp-key="F4"><span>🖨</span><span><kbd>F4</kbd> | Imprimir</span></button>
        </div>
    </div>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
