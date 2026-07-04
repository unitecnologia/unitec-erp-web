<div class="erp-ajusta-precos" wire:ignore.self>
    <div class="erp-ajusta-precos__locate">
        <span class="erp-ajusta-precos__locate-label">F6 | Localizar</span>
        <input type="text" wire:model.live.debounce.300ms="localSearch" class="erp-ajusta-precos__input" placeholder="DIGITE AQUI SUA PESQUISA">
    </div>
    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
