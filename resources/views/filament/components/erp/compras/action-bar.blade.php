<div class="erp-compras-actions">
    <button
        type="button"
        wire:click="createCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F2"
        @disabled($this->highlightedCompraDevolvida)
        title="{{ $this->highlightedCompraDevolvida ? 'Compra devolvida não permite entrada por XML' : 'Dar entrada de mercadoria pelo XML da NF-e (atalho F6 também)' }}"
    >
        <span class="erp-compras-actions__icon erp-compras-actions__icon--new">+</span>
        <span class="erp-compras-actions__label"><kbd>F2</kbd> | Entrada XML</span>
    </button>
    <button
        type="button"
        wire:click="openEntradaRomaneio"
        class="erp-compras-actions__btn"
        title="Dar entrada manual por romaneio"
    >
        <span class="erp-compras-actions__icon">▤</span>
        <span class="erp-compras-actions__label">Romaneio</span>
    </button>
    <button
        type="button"
        wire:click="editCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F3"
        @disabled($this->highlightedCompraDevolvida)
        title="{{ $this->highlightedCompraDevolvida ? 'Compra devolvida não pode ser alterada' : 'Alterar compra' }}"
    >
        <span class="erp-compras-actions__icon">✎</span>
        <span class="erp-compras-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button
        type="button"
        wire:click="cancelCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F4"
        @disabled($this->highlightedCompraDevolvida)
        title="{{ $this->highlightedCompraDevolvida ? 'Compra devolvida não pode ser cancelada' : 'Cancelar compra aberta (fechada: use F7 Reabrir antes)' }}"
    >
        <span class="erp-compras-actions__icon erp-compras-actions__icon--cancel">✕</span>
        <span class="erp-compras-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button
        type="button"
        wire:click="reabrirCompra"
        wire:confirm="Reabrir esta compra? Estoque, preços e contas a pagar gerados na finalização serão estornados."
        class="erp-compras-actions__btn"
        data-erp-key="F7"
        @disabled($this->highlightedCompraDevolvida)
        title="{{ $this->highlightedCompraDevolvida ? 'Compra devolvida não pode ser reaberta' : 'Reabrir compra fechada para editar novamente' }}"
    >
        <span class="erp-compras-actions__icon">↩</span>
        <span class="erp-compras-actions__label"><kbd>F7</kbd> | Reabrir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-compras-actions__btn" data-erp-key="F5">
        <span class="erp-compras-actions__icon">↻</span>
        <span class="erp-compras-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="openCompraContadorEmailModal" class="erp-compras-actions__btn" data-erp-key="F9" title="Enviar pacote mensal de compras ao contador">
        <span class="erp-compras-actions__icon">📅</span>
        <span class="erp-compras-actions__label"><kbd>F9</kbd> | Fechar Mês</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-compras-actions__btn erp-compras-actions__btn--close">
        <span class="erp-compras-actions__icon erp-compras-actions__icon--close">✕</span>
        <span class="erp-compras-actions__label">Fechar</span>
    </button>
</div>
