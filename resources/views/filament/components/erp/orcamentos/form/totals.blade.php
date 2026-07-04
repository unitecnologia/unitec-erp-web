<div class="erp-orc-totals">
    <span class="erp-orc-totals__label">SUBTOTAL |</span>
    <input type="text" readonly wire:model="subtotalDisplay" class="erp-orc-totals__value">

    <span class="erp-orc-totals__label">DESCONTO %</span>
    <input
        type="text"
        wire:model="percentualDescontoDisplay"
        wire:blur="applyDescontoFromPercentual"
        @disabled($this->orcamentoReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
    >

    <span class="erp-orc-totals__label">R$</span>
    <input
        type="text"
        wire:model="descontoValorDisplay"
        wire:blur="applyDescontoFromValor"
        @disabled($this->orcamentoReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
    >

    <span class="erp-orc-totals__label erp-orc-totals__label--total">TOTAL |</span>
    <input type="text" readonly wire:model="totalDisplay" class="erp-orc-totals__value erp-orc-totals__value--total">
</div>
