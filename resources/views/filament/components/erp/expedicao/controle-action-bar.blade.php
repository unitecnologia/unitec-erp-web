<div class="erp-nfe-actions erp-expedicao-actions">
    <button type="button" wire:click="marcarTodos" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">☑</span>
        <span class="erp-nfe-actions__label">Marcar todos</span>
    </button>
    <button type="button" wire:click="desmarcarTodos" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="2"/>
                <path d="M9 9l6 6M15 9l-6 6"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Desmarcar todos</span>
    </button>
    <button type="button" wire:click="confirmarSelecionados" class="erp-nfe-actions__btn" data-erp-key="F9">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Confirmar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn" data-erp-key="F5">
        <span class="erp-nfe-actions__icon">↻</span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Sair</span>
    </button>
</div>
