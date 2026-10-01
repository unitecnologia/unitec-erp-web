<div class="erp-produtos-pcad__footer erp-config-fiscais-pcad__footer">
    <div class="erp-pcad-actions">
        <button
            type="button"
            wire:click="saveConfig"
            wire:loading.attr="disabled"
            wire:target="saveConfig"
            class="erp-pcad-actions__btn"
            data-erp-key="F2"
        >
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
            <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveConfig"><kbd>F2</kbd> | Gravar</span>
            <span class="erp-pcad-actions__label" wire:loading wire:target="saveConfig">Gravando…</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-pcad-actions__btn" data-erp-key="Escape">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
        </button>
    </div>
</div>
