<div class="erp-compras-actions">
    <button
        type="button"
        wire:click="createCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F2"
        title="Dar entrada de mercadoria pelo XML da NF-e (atalho F6 também)"
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
        data-erp-disable-on-row="erp-compra-row--devolvida erp-compra-row--fechada erp-compra-row--cancelada"
        @disabled(! $this->canAlterarHighlightedCompra)
        title="{{ $this->canAlterarHighlightedCompra ? 'Alterar compra' : 'Compra fechada/cancelada/devolvida: use F7 Reabrir quando aplicável' }}"
    >
        <span class="erp-compras-actions__icon">✎</span>
        <span class="erp-compras-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button
        type="button"
        wire:click="cancelCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F4"
        data-erp-disable-on-row="erp-compra-row--devolvida"
        title="Cancelar compra aberta (fechada: use F7 Reabrir antes; devolvida: bloqueado)"
    >
        <span class="erp-compras-actions__icon erp-compras-actions__icon--cancel">✕</span>
        <span class="erp-compras-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button
        type="button"
        wire:click="reabrirCompra"
        class="erp-compras-actions__btn"
        data-erp-key="F7"
        data-erp-disable-on-row="erp-compra-row--devolvida"
        title="Reabrir compra fechada para editar novamente (devolvida: bloqueado)"
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
