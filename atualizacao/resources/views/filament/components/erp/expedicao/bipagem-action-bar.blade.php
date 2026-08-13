<div class="erp-nfe-actions erp-expedicao-actions">
    <button type="button" wire:click="estornarSelecionados" class="erp-nfe-actions__btn" data-erp-key="F6">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">↶</span>
        <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Estornar selecionados</span>
    </button>
    <button type="button" wire:click="marcarDesmarcarTodos" class="erp-nfe-actions__btn" data-erp-key="F8">
        <span class="erp-nfe-actions__icon">☑</span>
        <span class="erp-nfe-actions__label"><kbd>F8</kbd> | Marcar/desmarcar todos</span>
    </button>
    <button type="button" wire:click="voltarControle" class="erp-nfe-actions__btn" data-erp-key="Escape">
        <span class="erp-nfe-actions__label"><kbd>ESC</kbd> | Monitor de Expedição</span>
    </button>
    <button
        type="button"
        wire:click="confirmarExpedicao"
        class="erp-nfe-actions__btn"
        @disabled(! $this->podeConfirmarExpedicao())
        @if ($this->podeConfirmarExpedicao()) data-erp-key="F9" @endif
        title="{{ $this->podeConfirmarExpedicao() ? 'Confirmar expedição' : 'Bipe todos os produtos para confirmar' }}"
    >
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Confirmar expedição</span>
    </button>
    <button type="button" wire:click="imprimirSeparacao" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9V3h12v6"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="7" rx="1"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Imprimir separação</span>
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
