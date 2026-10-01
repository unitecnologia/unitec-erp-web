<div class="erp-devvenda-actions">
    <button type="button" wire:click="gravarDevolucao" class="erp-devvenda-actions__btn erp-devvenda-actions__btn--save" data-erp-key="F2" @disabled($this->devolucaoTravada)>
        <span class="erp-devvenda-actions__icon" aria-hidden="true">✓</span>
        <span class="erp-devvenda-actions__label"><kbd>F2</kbd> | Gravar</span>
    </button>
    <button type="button" wire:click="finalizarDevolucao" class="erp-devvenda-actions__btn erp-devvenda-actions__btn--finish" data-erp-key="F3" @disabled($this->devolucaoTravada)>
        <span class="erp-devvenda-actions__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>
        </span>
        <span class="erp-devvenda-actions__label"><kbd>F3</kbd> | Finalizar</span>
    </button>
    <button
        type="button"
        class="erp-devvenda-actions__btn erp-devvenda-actions__btn--print"
        onclick="window.print()"
        title="Imprimir vale"
    >
        <span class="erp-devvenda-actions__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M7 8V3h10v5"/><path d="M7 17H5a2 2 0 0 1-2-2v-5h18v5a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7z"/></svg>
        </span>
        <span class="erp-devvenda-actions__label">Imprimir vale</span>
    </button>
    <button type="button" wire:click="handleDevolucaoFormEscape" class="erp-devvenda-actions__btn erp-devvenda-actions__btn--exit" data-erp-key="Escape">
        <span class="erp-devvenda-actions__icon" aria-hidden="true">✕</span>
        <span class="erp-devvenda-actions__label"><kbd>ESC</kbd> | Sair</span>
    </button>
</div>
