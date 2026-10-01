<div class="erp-comissoes__actions">
    <button type="button" class="erp-comissoes__btn erp-comissoes__btn--primary" wire:click="calcularComissao" wire:loading.attr="disabled">
        Calcular
    </button>
    <button type="button" class="erp-comissoes__btn erp-comissoes__btn--primary" wire:click="fecharComissao" wire:loading.attr="disabled">
        Fechar comissão
    </button>
    <button type="button" class="erp-comissoes__btn" wire:click="abrirCap">Abrir CAP</button>
    <button type="button" class="erp-comissoes__btn erp-comissoes__btn--danger" wire:click="abrirCancelarComissao">Cancelar</button>
</div>
