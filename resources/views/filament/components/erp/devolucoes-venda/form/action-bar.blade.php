<div class="erp-devvenda-actions">
    <button type="button" wire:click="gravarDevolucao" class="erp-devvenda-actions__btn erp-devvenda-actions__btn--save" data-erp-key="F2">
        <span class="erp-devvenda-actions__icon">✓</span>
        <span class="erp-devvenda-actions__label"><kbd>F2</kbd> | Gravar</span>
    </button>
    <button type="button" wire:click="finalizarDevolucao" class="erp-devvenda-actions__btn" data-erp-key="F3">
        <span class="erp-devvenda-actions__icon">📄</span>
        <span class="erp-devvenda-actions__label"><kbd>F3</kbd> | Finalizar</span>
    </button>
    <button type="button" wire:click="handleDevolucaoFormEscape" class="erp-devvenda-actions__btn erp-devvenda-actions__btn--exit" data-erp-key="Escape">
        <span class="erp-devvenda-actions__icon">✕</span>
        <span class="erp-devvenda-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
