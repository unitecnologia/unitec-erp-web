<div class="erp-rh-documentos-actions">
    <button type="button" wire:click="createLote" class="erp-rh-documentos-actions__btn" data-erp-key="F2">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--new">+</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="verLote" class="erp-rh-documentos-actions__btn" data-erp-key="F3">
        <span class="erp-rh-documentos-actions__icon">👁</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F3</kbd> | Consultar</span>
    </button>
    <button type="button" wire:click="marcarConferido" class="erp-rh-documentos-actions__btn">
        <span class="erp-rh-documentos-actions__icon">✓</span>
        <span class="erp-rh-documentos-actions__label">Conferido</span>
    </button>
    <button type="button" wire:click="abrirOfxVinculo" class="erp-rh-documentos-actions__btn" title="Vincular crédito OFX (controle)">
        <span class="erp-rh-documentos-actions__icon">⇄</span>
        <span class="erp-rh-documentos-actions__label">OFX</span>
    </button>
    <button type="button" wire:click="abrirOfxHistorico" class="erp-rh-documentos-actions__btn" title="Histórico de vínculos OFX">
        <span class="erp-rh-documentos-actions__icon">☰</span>
        <span class="erp-rh-documentos-actions__label">Hist. OFX</span>
    </button>
    <button type="button" wire:click="abrirDemonstrativo" class="erp-rh-documentos-actions__btn" title="Demonstrativo de liquidações (consulta)">
        <span class="erp-rh-documentos-actions__icon">Σ</span>
        <span class="erp-rh-documentos-actions__label">Demonstrativo</span>
    </button>
    <button type="button" wire:click="cancelarLoteAsk" class="erp-rh-documentos-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--cancel">✕</span>
        <span class="erp-rh-documentos-actions__label"><kbd>Del</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-documentos-actions__btn" data-erp-key="F5">
        <span class="erp-rh-documentos-actions__icon">↻</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-documentos-actions__btn erp-rh-documentos-actions__btn--close">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--close">✕</span>
        <span class="erp-rh-documentos-actions__label">Fechar</span>
    </button>
</div>
