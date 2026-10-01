<div class="erp-nfe-actions erp-fv-tv-actions">
    <button type="button" wire:click="abrirImportarOrcamento" class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--import" data-erp-key="F2" title="Importar orçamento finalizado">
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <path d="M14 2v6h6"/>
                <path d="M12 18v-6"/>
                <path d="M9 15l3 3 3-3"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> Importar orçamento</span>
    </button>
    <button
        type="button"
        wire:click="gravarPedidoPendente"
        wire:loading.attr="disabled"
        wire:target="gravarPedidoPendente"
        class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--pendente"
        data-erp-key="F3"
        title="Gravar pedido como pendente (sem meio de pagamento)"
    >
        <span class="erp-fv-tv-btn__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <path d="M17 21v-8H7v8"/>
                <path d="M7 3v5h8"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">
            <kbd>F3</kbd>
            <span wire:loading.remove wire:target="gravarPedidoPendente">Pendente</span>
            <span wire:loading wire:target="gravarPedidoPendente">Gravando…</span>
        </span>
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
