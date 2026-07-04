<div class="erp-nfe-actions erp-fv-mon-actions">
    <button type="button" wire:click="modulePending('Imprimir Pedido')" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9V3h12v6"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="7" rx="1"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Imprimir</span>
    </button>
    <button type="button" wire:click="modulePending('Gerar Boleto')" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                <path d="M4 5v14M8 5v14M11 5v14M14 5v14M17 5v14M20 5v14"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Gerar Boleto</span>
    </button>
    <button type="button" wire:click="telaVenda" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="20" r="1.4"/>
                <circle cx="18" cy="20" r="1.4"/>
                <path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L23 7H6"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Tela de Venda</span>
    </button>
    <button type="button" wire:click="modulePending('Emitir NF-e')" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9 13h6M9 17h4"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Emitir NF-e</span>
    </button>
    <button type="button" wire:click="modulePending('Emitir NFC-e')" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1z"/>
                <path d="M9 8h6M9 12h6"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Emitir NFC-e</span>
    </button>

    <button type="button" wire:click="selecionarPendentes" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 11l3 3 7-7"/>
                <path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Marcar pendentes</span>
    </button>
    <button type="button" wire:click="limparSelecao" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="2"/>
                <path d="M9 9l6 6M15 9l-6 6"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Limpar seleção</span>
    </button>

    <button type="button" wire:click="cancelarPedidoPendente" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Cancelar pendente</span>
    </button>
    <button type="button" wire:click="estornarSelecionados" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Estornar</span>
    </button>
    <button type="button" wire:click="reabrirPedido" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/>
                <path d="M3 3v5h5"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Reabrir</span>
    </button>
    <button type="button" wire:click="faturarSelecionados" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Faturar</span>
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
