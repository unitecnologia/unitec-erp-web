<div class="erp-unidades-actions erp-recibos-actions">
    @if (erp_can('recibos.create'))
        <button type="button" wire:click="createRecibo" class="erp-unidades-actions__btn" data-erp-key="F2" title="Novo (F2)">
            <span class="erp-unidades-actions__icon erp-unidades-actions__icon--new">+</span>
            <span class="erp-unidades-actions__label">Novo</span>
        </button>
    @endif
    @if (erp_can('recibos.update'))
        <button type="button" wire:click="editRecibo" class="erp-unidades-actions__btn" data-erp-key="F3" title="Alterar (F3)">
            <span class="erp-unidades-actions__icon">✎</span>
            <span class="erp-unidades-actions__label">Alterar</span>
        </button>
    @endif
    @if (erp_can('recibos.print'))
        <button type="button" wire:click="imprimirRecibo" class="erp-unidades-actions__btn" data-erp-key="F6" title="Imprimir (F6)">
            <span class="erp-unidades-actions__icon">🖨</span>
            <span class="erp-unidades-actions__label">Imprimir</span>
        </button>
        <button type="button" wire:click="openEmailModal" class="erp-unidades-actions__btn" data-erp-key="F9" title="Enviar recibo por e-mail ou WhatsApp (F9)">
            <span class="erp-unidades-actions__icon">✉</span>
            <span class="erp-unidades-actions__label">Enviar</span>
        </button>
    @endif
    <button type="button" wire:click="closeScreen" class="erp-unidades-actions__btn erp-unidades-actions__btn--close" title="Fechar">
        <span class="erp-unidades-actions__icon erp-unidades-actions__icon--close">✕</span>
        <span class="erp-unidades-actions__label">Fechar</span>
    </button>
</div>
