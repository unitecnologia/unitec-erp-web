<div class="erp-contas-caixa-actions">
    @if (erp_can('contas_caixa.create'))
        <button type="button" wire:click="createContaCaixa" class="erp-contas-caixa-actions__btn erp-contas-caixa-actions__btn--primary" data-erp-key="F2" title="Novo (F2)">
            <span class="erp-contas-caixa-actions__icon">+</span>
            <span class="erp-contas-caixa-actions__label"><kbd>F2</kbd> Novo</span>
        </button>
    @endif
    @if (erp_can('contas_caixa.update'))
        <button type="button" wire:click="editContaCaixa" class="erp-contas-caixa-actions__btn" data-erp-key="F3" title="Alterar (F3)">
            <span class="erp-contas-caixa-actions__icon">A</span>
            <span class="erp-contas-caixa-actions__label"><kbd>F3</kbd> Alterar</span>
        </button>
    @endif
    @if (erp_can('contas_caixa.print'))
        <button type="button" wire:click="modulePending('Imprimir')" class="erp-contas-caixa-actions__btn" data-erp-key="F4" title="Imprimir (F4)">
            <span class="erp-contas-caixa-actions__icon">P</span>
            <span class="erp-contas-caixa-actions__label"><kbd>F4</kbd> Imprimir</span>
        </button>
    @endif
    <button type="button" wire:click="closeScreen" class="erp-contas-caixa-actions__btn erp-contas-caixa-actions__btn--close" title="Fechar">
        <span class="erp-contas-caixa-actions__icon">X</span>
        <span class="erp-contas-caixa-actions__label">Fechar</span>
    </button>
</div>
