<div class="erp-pcad-actions erp-pcad-actions--split">
    <button
        type="button"
        wire:click="saveForm"
        wire:loading.attr="disabled"
        wire:target="saveForm"
        class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
        data-erp-key="F5"
    >
        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
        <span wire:loading.remove wire:target="saveForm" class="erp-pcad-actions__label"><kbd>F5</kbd> | Gravar</span>
        <span wire:loading wire:target="saveForm" class="erp-pcad-actions__label">Gravando…</span>
    </button>
    <button type="button" wire:click="cancelForm" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger" data-erp-key="Escape">
        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
