<div class="erp-nfe-actions erp-fv-tv-actions">
    <button type="button" wire:click="pedirConfirmacaoExcluirItem" class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--warn" title="Exclui o item selecionado (Delete)">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 7h16"/>
                <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                <path d="M7 7l1 12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-12"/>
                <path d="M10 11v6M14 11v6"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>Del</kbd> Excluir item</span>
    </button>
    <button type="button" wire:click="irParaFinalizacao" class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--ok" data-erp-key="F4">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> Fechar</span>
    </button>
    <button type="button" wire:click="cancelarVenda" class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--cancel" data-erp-key="F5">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 6l12 12M18 6 6 18"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> Cancelar</span>
    </button>

    <button type="button" wire:click="sair" class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--exit">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Sair</span>
    </button>
</div>
