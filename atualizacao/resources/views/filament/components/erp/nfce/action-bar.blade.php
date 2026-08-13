<div class="erp-nfe-actions">
    <button type="button" wire:click="cancelarNfce" class="erp-nfe-actions__btn" data-erp-key="F2">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">✕</span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="inutilizarNfce" class="erp-nfe-actions__btn" data-erp-key="F3">
        <span class="erp-nfe-actions__icon">🚫</span>
        <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Inutilizar</span>
    </button>
    <button type="button" wire:click="recuperarNfce" class="erp-nfe-actions__btn" data-erp-key="F4">
        <span class="erp-nfe-actions__icon">↩</span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Recuperar</span>
    </button>
    @if ($this->statusFilter === \App\Models\PdvVendaNfce::TAB_CONTINGENCIA)
        <button type="button" wire:click="marcarDesmarcarNfcesContingencia" class="erp-nfe-actions__btn">
            <span class="erp-nfe-actions__icon">☑</span>
            <span class="erp-nfe-actions__label">Marcar/desmarcar todos</span>
        </button>
    @endif
    <button
        type="button"
        wire:click="transmitirNfce"
        wire:loading.attr="disabled"
        wire:target="transmitirNfce"
        class="erp-nfe-actions__btn"
        data-erp-key="F5"
    >
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--transmit">📡</span>
        <span wire:loading.remove wire:target="transmitirNfce" class="erp-nfe-actions__label"><kbd>F5</kbd> | Transmitir</span>
        <span wire:loading wire:target="transmitirNfce" class="erp-nfe-actions__label">Transmitindo…</span>
    </button>
    <button type="button" wire:click="imprimirNfce" class="erp-nfe-actions__btn" data-erp-key="F6">
        <span class="erp-nfe-actions__icon">🖨</span>
        <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="printNfceRelatorio" class="erp-nfe-actions__btn" data-erp-key="F7">
        <span class="erp-nfe-actions__icon">📊</span>
        <span class="erp-nfe-actions__label"><kbd>F7</kbd> | Relatório</span>
    </button>
    <button type="button" wire:click="openNfceClienteEmailModal" class="erp-nfe-actions__btn" data-erp-key="F8">
        <span class="erp-nfe-actions__icon">✉</span>
        <span class="erp-nfe-actions__label"><kbd>F8</kbd> | Email</span>
    </button>
    <button type="button" class="erp-nfe-actions__btn" disabled title="Em breve">
        <span class="erp-nfe-actions__icon">🗂</span>
        <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Agrupar</span>
    </button>
    <button type="button" wire:click="openNfceContadorEmailModal" class="erp-nfe-actions__btn" data-erp-key="F11">
        <span class="erp-nfe-actions__icon">📄</span>
        <span class="erp-nfe-actions__label"><kbd>F11</kbd> | Gerar PDF</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">↻</span>
        <span class="erp-nfe-actions__label">Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">✕</span>
        <span class="erp-nfe-actions__label">Fechar</span>
    </button>
</div>
