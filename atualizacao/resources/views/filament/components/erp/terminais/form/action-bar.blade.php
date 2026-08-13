<div class="erp-produtos-pcad__footer erp-terminais-pcad__footer">
    <div class="erp-pcad-actions erp-terminais-pcad__actions">
        <button type="button" wire:click="deleteTerminal" class="erp-pcad-actions__btn" data-erp-key="F4">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Excluir Terminal</span>
        </button>
        <button type="button" wire:click="saveTerminalForm" class="erp-pcad-actions__btn" data-erp-key="F10">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
            <span class="erp-pcad-actions__label"><kbd>F10</kbd> | Salvar</span>
        </button>
        <button type="button" wire:click="closeScreen" class="erp-pcad-actions__btn" data-erp-key="Escape">
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
        </button>
    </div>
</div>
