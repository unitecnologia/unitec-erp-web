<div class="erp-nfe-actions">
    <button type="button" wire:click="createNfe" class="erp-nfe-actions__btn" data-erp-key="F2">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">+</span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editNfe" class="erp-nfe-actions__btn" data-erp-key="F3">
        <span class="erp-nfe-actions__icon">✎</span>
        <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="modulePending('Cancelar NF-e')" class="erp-nfe-actions__btn" data-erp-key="F4">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">✕</span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Cancelar</span>
    </button>
    <button type="button" wire:click="modulePending('Inutilizar')" class="erp-nfe-actions__btn" data-erp-key="F5">
        <span class="erp-nfe-actions__icon">🚫</span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> | Inutilizar</span>
    </button>
    <button type="button" wire:click="modulePending('Recuperar')" class="erp-nfe-actions__btn" data-erp-key="F6">
        <span class="erp-nfe-actions__icon">↩</span>
        <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Recuperar</span>
    </button>
    <button type="button" wire:click="modulePending('Imprimir DANFE')" class="erp-nfe-actions__btn" data-erp-key="F7">
        <span class="erp-nfe-actions__icon">🖨</span>
        <span class="erp-nfe-actions__label"><kbd>F7</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="modulePending('Carta de Correção')" class="erp-nfe-actions__btn" data-erp-key="F8">
        <span class="erp-nfe-actions__icon">📝</span>
        <span class="erp-nfe-actions__label"><kbd>F8</kbd> | CCe</span>
    </button>
    <button type="button" wire:click="modulePending('Email')" class="erp-nfe-actions__btn" data-erp-key="F9">
        <span class="erp-nfe-actions__icon">✉</span>
        <span class="erp-nfe-actions__label"><kbd>F9</kbd> | Email</span>
    </button>
    <button type="button" wire:click="modulePending('Relatório')" class="erp-nfe-actions__btn" data-erp-key="F10">
        <span class="erp-nfe-actions__icon">📊</span>
        <span class="erp-nfe-actions__label"><kbd>F10</kbd> | Relatório</span>
    </button>
    <button type="button" wire:click="modulePending('WhatsApp')" class="erp-nfe-actions__btn" data-erp-key="F11">
        <span class="erp-nfe-actions__icon">📱</span>
        <span class="erp-nfe-actions__label"><kbd>F11</kbd> | Whats</span>
    </button>
    <button type="button" wire:click="modulePending('Fechar Mês')" class="erp-nfe-actions__btn" data-erp-key="F12">
        <span class="erp-nfe-actions__icon">📅</span>
        <span class="erp-nfe-actions__label"><kbd>F12</kbd> | Fechar Mês</span>
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
