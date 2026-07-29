<div class="erp-ajusta-precos-actions">
    <button
        type="button"
        wire:click="pesquisar"
        class="erp-ajusta-precos-actions__btn"
        data-erp-key="F5"
        title="Efetuar consulta (F5)"
    >
        <span class="erp-ajusta-precos-actions__icon">↻</span>
        <span class="erp-ajusta-precos-actions__label"><kbd>F5</kbd> | Consulta</span>
    </button>
    <button
        type="button"
        wire:click="marcarOuInverterTodos"
        class="erp-ajusta-precos-actions__btn"
        data-erp-key="F2"
        title="Marcar todos / inverter (F2)"
    >
        <span class="erp-ajusta-precos-actions__icon">☑</span>
        <span class="erp-ajusta-precos-actions__label"><kbd>F2</kbd> | Marcar</span>
    </button>
    <button
        type="button"
        wire:click="closeScreen"
        class="erp-ajusta-precos-actions__btn erp-ajusta-precos-actions__btn--close"
    >
        <span class="erp-ajusta-precos-actions__icon erp-ajusta-precos-actions__icon--close">✕</span>
        <span class="erp-ajusta-precos-actions__label">Fechar</span>
    </button>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/ajusta-precos/action-bar.blade.php ENDPATH**/ ?>