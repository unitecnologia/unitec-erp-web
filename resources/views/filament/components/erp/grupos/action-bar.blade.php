<div class="erp-grupos-actions">
    <button type="button" wire:click="createGrupo" class="erp-grupos-actions__btn" data-erp-key="F2"><span class="erp-grupos-actions__icon erp-grupos-actions__icon--new">+</span><span class="erp-grupos-actions__label"><kbd>F2</kbd> | Novo</span></button>
    <button type="button" wire:click="editGrupo" class="erp-grupos-actions__btn" data-erp-key="F3"><span class="erp-grupos-actions__icon">✎</span><span class="erp-grupos-actions__label"><kbd>F3</kbd> | Alterar</span></button>
    <button type="button" wire:click="modulePending('Imprimir')" class="erp-grupos-actions__btn" data-erp-key="F4"><span class="erp-grupos-actions__icon">🖨</span><span class="erp-grupos-actions__label"><kbd>F4</kbd> | Imprimir</span></button>
    <button type="button" wire:click="refreshTable" class="erp-grupos-actions__btn" data-erp-key="F5"><span class="erp-grupos-actions__icon">↻</span><span class="erp-grupos-actions__label"><kbd>F5</kbd> | Atualizar</span></button>
    <button type="button" wire:click="closeScreen" class="erp-grupos-actions__btn erp-grupos-actions__btn--close"><span class="erp-grupos-actions__icon erp-grupos-actions__icon--close">✕</span><span class="erp-grupos-actions__label">Fechar</span></button>
</div>
